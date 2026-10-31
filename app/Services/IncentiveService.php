<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\UserStreak;
use App\Models\UserTaskLog;
use App\Models\DailyTask;
use App\Models\Saldo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class IncentiveService
{
    /**
     * Get or create wallet for user
     */
    public function getOrCreateWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->user_id],
            [
                'wallet_ad'     => 0.00,
                'wallet_dinkes' => 0.00,
            ]
        );
    }

    /**
     * Get or create streak for user
     */
    public function getOrCreateStreak(User $user): UserStreak
    {
        return UserStreak::firstOrCreate(
            ['user_id' => $user->user_id],
            [
                'current_streak' => 0,
                'longest_streak' => 0,
            ]
        );
    }

    /**
     * Award points to user wallet and advance healthy streak
     *
     * @param User $user
     * @param float $points
     * @param string $sof 'dinkes' or 'ad'
     * @param string $description
     * @param int|null $dailyTaskId
     * @return array
     */
    public function awardPoints(
        User $user,
        float $points,
        string $sof = 'dinkes',
        string $description = 'Reward Kepatuhan Maternal',
        ?int $dailyTaskId = null
    ): array {
        return DB::transaction(function () use ($user, $points, $sof, $description, $dailyTaskId) {
            $today     = Carbon::today()->toDateString();
            $yesterday = Carbon::yesterday()->toDateString();

            // 1. Update Wallets & Transactions
            $wallet = $this->getOrCreateWallet($user);
            $transaction = null;

            if ($sof === 'dinkes') {
                $transaction = $wallet->creditDinkes($points);
            } else {
                $transaction = $wallet->creditAd($points);
            }

            // 2. Advance User Healthy Streak
            $streak = $this->getOrCreateStreak($user);
            $streakAdvanced = false;

            if ($streak->last_activity_date !== $today) {
                $streak->current_streak = ($streak->last_activity_date === $yesterday)
                    ? $streak->current_streak + 1
                    : 1;

                if ($streak->current_streak > $streak->longest_streak) {
                    $streak->longest_streak = $streak->current_streak;
                }

                $streak->last_activity_date = $today;
                $streak->save();
                $streakAdvanced = true;
            }

            // 3. Log Daily Task if provided
            $taskLog = null;
            if ($dailyTaskId) {
                $existingLog = UserTaskLog::where('user_id', $user->user_id)
                    ->where('daily_task_id', $dailyTaskId)
                    ->whereDate('completed_date', $today)
                    ->first();

                if (!$existingLog) {
                    $taskLog = UserTaskLog::create([
                        'user_id'        => $user->user_id,
                        'daily_task_id'  => $dailyTaskId,
                        'completed_date' => $today,
                    ]);
                }
            }

            // 4. Synchronize with legacy Saldo ledger
            Saldo::create([
                'user_id'    => $user->user_id,
                'amount'     => $points,
                'type'       => 'credit',
                'status'     => 'active',
                'keterangan' => 'Insentif: ' . $description,
            ]);

            return [
                'awarded_points' => $points,
                'sof'            => $sof,
                'description'    => $description,
                'balance_dinkes' => (float) $wallet->wallet_dinkes,
                'balance_ad'     => (float) $wallet->wallet_ad,
                'total_balance'  => (float) ($wallet->wallet_dinkes + $wallet->wallet_ad),
                'current_streak' => $streak->current_streak,
                'longest_streak' => $streak->longest_streak,
                'streak_advanced'=> $streakAdvanced,
                'transaction_id' => $transaction?->wallet_transactions_id,
                'date'           => $today,
            ];
        });
    }

    /**
     * Verify food plate nutrition compliance and reward if score >= 70%
     */
    public function verifyAndRewardFood(
        User $user,
        float $complianceScore,
        ?string $photoUrl = null,
        array $foodItems = []
    ): array {
        $today = Carbon::today()->toDateString();
        $foodTask = DailyTask::where('task_type', 'food_compliance')->first();
        $taskId = $foodTask?->id;

        // Check if already claimed food reward today
        $alreadyClaimed = false;
        if ($taskId) {
            $alreadyClaimed = UserTaskLog::where('user_id', $user->user_id)
                ->where('daily_task_id', $taskId)
                ->whereDate('completed_date', $today)
                ->exists();
        }

        $minThreshold = 0.70;
        $isEligible = ($complianceScore >= $minThreshold);

        if (!$isEligible) {
            $wallet = $this->getOrCreateWallet($user);
            $streak = $this->getOrCreateStreak($user);

            return [
                'status'           => 'warning',
                'is_eligible'      => false,
                'compliance_score' => $complianceScore,
                'earned_points'    => 0,
                'message'          => 'Komposisi gizi piring Anda (' . round($complianceScore * 100) . '%) belum memenuhi standar Kemenkes (min. 70%). Tingkatkan porsi sayur & protein hewani untuk mengklaim reward.',
                'wallet'           => [
                    'wallet_dinkes' => (float) $wallet->wallet_dinkes,
                    'current_streak'=> $streak->current_streak,
                ],
            ];
        }

        if ($alreadyClaimed) {
            $wallet = $this->getOrCreateWallet($user);
            $streak = $this->getOrCreateStreak($user);

            return [
                'status'           => 'success',
                'is_eligible'      => true,
                'already_claimed'  => true,
                'compliance_score' => $complianceScore,
                'earned_points'    => 0,
                'message'          => 'Piring gizi seimbang terverifikasi (' . round($complianceScore * 100) . '%). Reward harian hari ini sudah diklaim sebelumnya.',
                'wallet'           => [
                    'wallet_dinkes' => (float) $wallet->wallet_dinkes,
                    'current_streak'=> $streak->current_streak,
                ],
            ];
        }

        // Award +50 points
        $rewardResult = $this->awardPoints(
            $user,
            50.00,
            'dinkes',
            'Verifikasi Piring Gizi Seimbang Kemenkes (' . round($complianceScore * 100) . '%)',
            $taskId
        );

        return [
            'status'           => 'success',
            'is_eligible'      => true,
            'already_claimed'  => false,
            'compliance_score' => $complianceScore,
            'earned_points'    => 50,
            'message'          => 'Selamat! Piring gizi seimbang terverifikasi (' . round($complianceScore * 100) . '%). +50 Koin Dinkes berhasil ditambahkan ke dompet Anda!',
            'reward'           => [
                'earned'         => 50,
                'balance_dinkes' => $rewardResult['balance_dinkes'],
                'current_streak' => $rewardResult['current_streak'],
                'longest_streak' => $rewardResult['longest_streak'],
            ],
            'food_items'       => $foodItems,
        ];
    }

    /**
     * Get complete wallet and streak summary for user
     */
    public function getWalletSummary(User $user): array
    {
        $wallet = $this->getOrCreateWallet($user);
        $streak = $this->getOrCreateStreak($user);

        $transactions = WalletTransaction::where('wallets_id', $wallet->wallets_id)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $today = Carbon::today()->toDateString();
        $completedTaskIds = UserTaskLog::where('user_id', $user->user_id)
            ->whereDate('completed_date', $today)
            ->pluck('daily_task_id')
            ->toArray();

        return [
            'wallets_id'      => $wallet->wallets_id,
            'user_id'         => $user->user_id,
            'wallet_dinkes'   => (float) $wallet->wallet_dinkes,
            'wallet_ad'       => (float) $wallet->wallet_ad,
            'total_balance'   => (float) ($wallet->wallet_dinkes + $wallet->wallet_ad),
            'current_streak'  => $streak->current_streak,
            'longest_streak'  => $streak->longest_streak,
            'last_active'     => $streak->last_activity_date,
            'today_completed_tasks_count' => count($completedTaskIds),
            'recent_transactions' => $transactions->map(fn($t) => [
                'id'         => $t->wallet_transactions_id,
                'sof'        => $t->sof,
                'amount'     => (float) $t->amount,
                'trx_action' => $t->trx_action,
                'status'     => $t->status,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
        ];
    }
}
