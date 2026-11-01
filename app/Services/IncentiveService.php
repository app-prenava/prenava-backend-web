<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\UserStreak;
use App\Models\UserTaskLog;
use App\Models\DailyTask;
use App\Models\Saldo;
use App\Models\Badge;
use App\Models\UserBadge;
use App\Models\PointTransaction;
use App\Models\WaterIntake;
use App\Models\FoodScan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class IncentiveService
{
    /**
     * Standard timezone for Indonesia Western Time (WIB)
     */
    public const TIMEZONE = 'Asia/Jakarta';

    /**
     * Get current date in WIB
     */
    public function getToday(): string
    {
        return Carbon::now(self::TIMEZONE)->toDateString();
    }

    /**
     * Get yesterday date in WIB
     */
    public function getYesterday(): string
    {
        return Carbon::yesterday(self::TIMEZONE)->toDateString();
    }

    /**
     * Resolve user category automatically.
     * If user has active pregnancy record, auto-assigns 'ibu_hamil'.
     */
    public function resolveUserCategory(User $user): string
    {
        if (!empty($user->category)) {
            return $user->category;
        }

        // Cek apakah ada catatan kehamilan di tabel pregnancies
        $hasPregnancy = $user->pregnancies()->exists();
        if ($hasPregnancy) {
            return 'ibu_hamil';
        }

        return 'umum';
    }

    /**
     * Get or create wallet for user (legacy compatibility)
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
     * Calculate Maternal Tier based on lifetime points and current streak
     */
    public function calculateMaternalTier(int $lifetimePoints, int $streak): array
    {
        if ($lifetimePoints >= 3000 && $streak >= 30) {
            return [
                'tier'          => 'platinum',
                'name'          => 'Bunda Platinum',
                'icon'          => 'diamond',
                'color'         => '#7C4DFF',
                'next_tier'     => null,
                'points_needed' => 0,
                'streak_needed' => 0,
            ];
        } elseif ($lifetimePoints >= 1500 && $streak >= 14) {
            return [
                'tier'          => 'gold',
                'name'          => 'Bunda Emas',
                'icon'          => 'emoji_events',
                'color'         => '#FFB300',
                'next_tier'     => 'Bunda Platinum',
                'points_needed' => max(0, 3000 - $lifetimePoints),
                'streak_needed' => max(0, 30 - $streak),
            ];
        } elseif ($lifetimePoints >= 500 && $streak >= 7) {
            return [
                'tier'          => 'silver',
                'name'          => 'Bunda Perak',
                'icon'          => 'military_tech',
                'color'         => '#9E9E9E',
                'next_tier'     => 'Bunda Emas',
                'points_needed' => max(0, 1500 - $lifetimePoints),
                'streak_needed' => max(0, 14 - $streak),
            ];
        } else {
            return [
                'tier'          => 'bronze',
                'name'          => 'Bunda Perunggu',
                'icon'          => 'shield',
                'color'         => '#CD7F32',
                'next_tier'     => 'Bunda Perak',
                'points_needed' => max(0, 500 - $lifetimePoints),
                'streak_needed' => max(0, 7 - $streak),
            ];
        }
    }

    /**
     * Award points to user and advance healthy streak
     * Single source of truth: users.total_points & point_transactions
     */
    public function awardPoints(
        User $user,
        float $points,
        string $sof = 'dinkes',
        string $description = 'Reward Kepatuhan Maternal',
        ?int $dailyTaskId = null,
        string $actionType = 'daily_task'
    ): array {
        return DB::transaction(function () use ($user, $points, $sof, $description, $dailyTaskId, $actionType) {
            $today     = $this->getToday();
            $yesterday = $this->getYesterday();
            $intPoints = (int) round($points);

            // 1. Catat Poin di users dan point_transactions
            $user->addPoints(
                $intPoints,
                $actionType,
                $dailyTaskId ? (string) $dailyTaskId : null,
                $description
            );

            // 2. Advance User Healthy Streak (Kunci Zona Waktu WIB)
            $streak = $this->getOrCreateStreak($user);
            $streakAdvanced = false;

            if ($streak->last_activity_date?->toDateString() !== $today) {
                $lastDate = $streak->last_activity_date?->toDateString();
                $streak->current_streak = ($lastDate === $yesterday)
                    ? $streak->current_streak + 1
                    : 1;

                if ($streak->current_streak > $streak->longest_streak) {
                    $streak->longest_streak = $streak->current_streak;
                }

                $streak->last_activity_date = $today;
                $streak->save();
                $streakAdvanced = true;
            }

            // 3. Log Daily Task jika diberikan
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

            // 4. Kompatibilitas Legacy: update wallet dan saldos
            $wallet = $this->getOrCreateWallet($user);
            $transaction = ($sof === 'dinkes') ? $wallet->creditDinkes($points) : $wallet->creditAd($points);

            Saldo::create([
                'user_id'    => $user->user_id,
                'amount'     => $points,
                'type'       => 'credit',
                'status'     => 'active',
                'keterangan' => 'Insentif: ' . $description,
            ]);

            // Sinkronkan juga users.saldo_total agar tidak tampil 0 pada legacy endpoint
            $user->increment('saldo_total', $points);

            // 5. Evaluasi Lencana Prestasi (Badges)
            $newBadges = $this->evaluateBadges($user);

            // 6. Hitung status Maternal Tier terkini
            $tierInfo = $this->calculateMaternalTier((int) $user->total_points, (int) $streak->current_streak);

            return [
                'awarded_points'   => $intPoints,
                'total_points'     => (int) $user->total_points,
                'points_balance'   => (int) $user->points_balance,
                'tier_info'        => $tierInfo,
                'description'      => $description,
                'current_streak'   => $streak->current_streak,
                'longest_streak'   => $streak->longest_streak,
                'streak_advanced'  => $streakAdvanced,
                'new_badges'       => $newBadges,
                'transaction_id'   => $transaction?->wallet_transactions_id,
                'date'             => $today,
            ];
        });
    }

    /**
     * Record 1 glass of water intake (250 ml) for today WIB.
     * Completes daily hydration task when 8 glasses are reached.
     */
    public function recordHydrationGlass(User $user): array
    {
        $today = $this->getToday();

        return DB::transaction(function () use ($user, $today) {
            $intake = WaterIntake::firstOrCreate(
                [
                    'user_id' => $user->user_id,
                    'tanggal' => $today,
                ],
                [
                    'glass_count'       => 0,
                    'jumlah_ml'         => 0,
                    'is_target_reached' => false,
                ]
            );

            // Tambah 1 gelas
            $intake->glass_count += 1;
            $intake->jumlah_ml = $intake->glass_count * 250;

            $pointsEarned = 0;
            $isCompletedNow = false;

            if ($intake->glass_count >= 8 && !$intake->is_target_reached) {
                $intake->is_target_reached = true;
                $isCompletedNow = true;

                // Cari daily task hydration
                $task = DailyTask::where('task_type', 'hydration')->first();
                $taskId = $task?->id;
                $taskPoints = $task?->points ?? 10;

                // Cek apakah sudah dapat poin hari ini
                $alreadyLogged = false;
                if ($taskId) {
                    $alreadyLogged = UserTaskLog::where('user_id', $user->user_id)
                        ->where('daily_task_id', $taskId)
                        ->whereDate('completed_date', $today)
                        ->exists();
                }

                if (!$alreadyLogged) {
                    $this->awardPoints(
                        $user,
                        (float) $taskPoints,
                        'dinkes',
                        'Target Hidrasi 8 Gelas Air Terpenuhi',
                        $taskId,
                        'hydration'
                    );
                    $pointsEarned = $taskPoints;
                }
            }

            $intake->save();

            // Evaluasi badge hidrasi
            $newBadges = $this->evaluateBadges($user);

            return [
                'current_glasses'   => $intake->glass_count,
                'target_glasses'    => 8,
                'current_ml'        => $intake->jumlah_ml,
                'target_ml'         => 2000,
                'is_target_reached' => (bool) $intake->is_target_reached,
                'is_completed_now'  => $isCompletedNow,
                'points_earned'     => $pointsEarned,
                'new_badges'        => $newBadges,
            ];
        });
    }

    /**
     * Evaluate and unlock milestone badges for user
     */
    public function evaluateBadges(User $user): array
    {
        $today = $this->getToday();
        $streak = $this->getOrCreateStreak($user);
        $activeBadges = Badge::where('is_active', true)->get();
        $unlockedBadgeIds = UserBadge::where('user_id', $user->user_id)->pluck('badge_id')->toArray();

        $newlyUnlocked = [];

        foreach ($activeBadges as $badge) {
            if (in_array($badge->id, $unlockedBadgeIds)) {
                continue;
            }

            $isEligible = false;

            switch ($badge->requirement_type) {
                case 'streak_days':
                    $isEligible = ($streak->current_streak >= $badge->requirement_threshold)
                        || ($streak->longest_streak >= $badge->requirement_threshold);
                    break;

                case 'ttd_count':
                    // Hitung log tugas 'supplement' (Tablet Tambah Darah)
                    $ttdTaskId = DailyTask::where('task_type', 'supplement')->value('id');
                    if ($ttdTaskId) {
                        $count = UserTaskLog::where('user_id', $user->user_id)
                            ->where('daily_task_id', $ttdTaskId)
                            ->count();
                        $isEligible = ($count >= $badge->requirement_threshold);
                    }
                    break;

                case 'hydration_streak':
                    // Hitung berapa hari beruntun memenuhi target 8 gelas air
                    $reachedDays = WaterIntake::where('user_id', $user->user_id)
                        ->where('is_target_reached', true)
                        ->count();
                    $isEligible = ($reachedDays >= $badge->requirement_threshold);
                    break;

                case 'food_compliance_count':
                    // Hitung log tugas piring gizi atau scan food score >= 70
                    $foodTaskId = DailyTask::where('task_type', 'food_compliance')->value('id');
                    $count = 0;
                    if ($foodTaskId) {
                        $count = UserTaskLog::where('user_id', $user->user_id)
                            ->where('daily_task_id', $foodTaskId)
                            ->count();
                    }
                    $scanCount = FoodScan::where('user_id', $user->user_id)
                        ->where('nutrition_score', '>=', 70)
                        ->count();
                    $totalValid = max($count, $scanCount);
                    $isEligible = ($totalValid >= $badge->requirement_threshold);
                    break;

                case 'exercise_count':
                    $exerciseTaskId = DailyTask::where('task_type', 'exercise')->value('id');
                    if ($exerciseTaskId) {
                        $count = UserTaskLog::where('user_id', $user->user_id)
                            ->where('daily_task_id', $exerciseTaskId)
                            ->count();
                        $isEligible = ($count >= $badge->requirement_threshold);
                    }
                    break;

                case 'early_bird':
                    // Apakah ada tugas hari ini sebelum jam 08:00 WIB
                    $hour = (int) Carbon::now(self::TIMEZONE)->format('H');
                    if ($hour < 8) {
                        $todayTaskCount = UserTaskLog::where('user_id', $user->user_id)
                            ->whereDate('completed_date', $today)
                            ->count();
                        $isEligible = ($todayTaskCount >= 1);
                    }
                    break;
            }

            if ($isEligible) {
                UserBadge::create([
                    'user_id'     => $user->user_id,
                    'badge_id'    => $badge->id,
                    'unlocked_at' => Carbon::now(self::TIMEZONE),
                ]);

                // Berikan bonus poin jika ada
                if ($badge->bonus_points > 0) {
                    $user->addPoints(
                        $badge->bonus_points,
                        'badge_bonus',
                        $badge->badge_code,
                        'Bonus Lencana: ' . $badge->name
                    );
                }

                $newlyUnlocked[] = [
                    'badge_code'   => $badge->badge_code,
                    'name'         => $badge->name,
                    'category'     => $badge->category,
                    'description'  => $badge->description,
                    'icon'         => $badge->icon,
                    'bonus_points' => $badge->bonus_points,
                ];
            }
        }

        return $newlyUnlocked;
    }

    /**
     * Verify food plate nutrition compliance and reward if score >= 70%
     * Enhanced with FoodScan verification for anti-spoofing
     */
    public function verifyAndRewardFood(
        User $user,
        float $complianceScore,
        ?string $photoUrl = null,
        array $foodItems = [],
        ?int $foodScanId = null
    ): array {
        $today = $this->getToday();
        $foodTask = DailyTask::where('task_type', 'food_compliance')->first();
        $taskId = $foodTask?->id;

        // Anti-spoofing check jika foodScanId disertakan
        if ($foodScanId) {
            $scan = FoodScan::where('food_scan_id', $foodScanId)
                ->where('user_id', $user->user_id)
                ->first();

            if ($scan) {
                $complianceScore = max($complianceScore, ($scan->nutrition_score / 100.0));
            }
        }

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
            $streak = $this->getOrCreateStreak($user);

            return [
                'status'           => 'warning',
                'is_eligible'      => false,
                'compliance_score' => $complianceScore,
                'earned_points'    => 0,
                'message'          => 'Komposisi gizi piring Anda (' . round($complianceScore * 100) . '%) belum memenuhi standar Kemenkes (min. 70%). Tingkatkan porsi sayur & protein hewani untuk mengklaim reward.',
                'current_streak'   => $streak->current_streak,
            ];
        }

        if ($alreadyClaimed) {
            $streak = $this->getOrCreateStreak($user);

            return [
                'status'           => 'success',
                'is_eligible'      => true,
                'already_claimed'  => true,
                'compliance_score' => $complianceScore,
                'earned_points'    => 0,
                'message'          => 'Piring gizi seimbang terverifikasi (' . round($complianceScore * 100) . '%). Reward harian hari ini sudah diklaim sebelumnya.',
                'current_streak'   => $streak->current_streak,
            ];
        }

        // Award +50 points
        $rewardResult = $this->awardPoints(
            $user,
            50.00,
            'dinkes',
            'Verifikasi Piring Gizi Seimbang Kemenkes (' . round($complianceScore * 100) . '%)',
            $taskId,
            'daily_task'
        );

        return [
            'status'           => 'success',
            'is_eligible'      => true,
            'already_claimed'  => false,
            'compliance_score' => $complianceScore,
            'earned_points'    => 50,
            'total_points'     => $rewardResult['total_points'],
            'tier_info'        => $rewardResult['tier_info'],
            'message'          => 'Selamat! Piring gizi seimbang terverifikasi (' . round($complianceScore * 100) . '%). +50 Poin Sehat berhasil ditambahkan!',
            'reward'           => [
                'earned'         => 50,
                'current_streak' => $rewardResult['current_streak'],
                'longest_streak' => $rewardResult['longest_streak'],
                'new_badges'     => $rewardResult['new_badges'],
            ],
            'food_items'       => $foodItems,
        ];
    }

    /**
     * Get complete daily progress summary for user
     */
    public function getDailyProgressSummary(User $user): array
    {
        $today = $this->getToday();
        $userCategory = $this->resolveUserCategory($user);

        // Ambil tugas yang sesuai kategori bumil atau umum
        $tasks = DailyTask::where(function ($q) use ($userCategory) {
            $q->whereNull('target_category')
              ->orWhere('target_category', $userCategory);
        })->get();

        $completedTaskIds = UserTaskLog::where('user_id', $user->user_id)
            ->whereDate('completed_date', $today)
            ->pluck('daily_task_id')
            ->toArray();

        $streak = $this->getOrCreateStreak($user);
        $tierInfo = $this->calculateMaternalTier((int) $user->total_points, (int) $streak->current_streak);

        // Ambil data hidrasi hari ini
        $waterIntake = WaterIntake::where('user_id', $user->user_id)
            ->where('tanggal', $today)
            ->first();

        $currentGlasses = $waterIntake?->glass_count ?? 0;
        $isHydrationComplete = $currentGlasses >= 8;

        // Route mapping untuk action buttons di mobile
        $routeMap = [
            'hydration'              => '/hydration-tracker',
            'supplement'             => null, // checklist langsung di beranda
            'exercise'               => null,
            'food_compliance'        => '/food-scanner',
            'feature_anemia'         => '/anemia-detection',
            'feature_depresi'        => '/depression-detection',
            'feature_olahraga'       => '/sport-recommendation',
            'feature_komunitas'      => '/community',
            'feature_kalkulator_hpl' => '/pregnancy-calculator',
            'feature_tips'           => '/tips',
            'feature_udara'          => '/air-quality',
            'feature_local_wisdom'   => '/local-wisdom',
        ];

        $formattedTasks = $tasks->map(function ($task) use ($completedTaskIds, $isHydrationComplete, $routeMap) {
            $isCompleted = in_array($task->id, $completedTaskIds);
            if ($task->task_type === 'hydration' && $isHydrationComplete) {
                $isCompleted = true;
            }

            return [
                'id'           => $task->id,
                'title'        => $task->title,
                'description'  => $task->description,
                'task_type'    => $task->task_type,
                'points'       => (int) $task->points,
                'is_completed' => $isCompleted,
                'is_auto'      => str_starts_with($task->task_type, 'feature_'),
                'action_route' => $routeMap[$task->task_type] ?? null,
            ];
        });

        $unlockedBadgesCount = UserBadge::where('user_id', $user->user_id)->count();

        return [
            'user_points' => [
                'current_balance' => (int) $user->points_balance,
                'lifetime_points' => (int) $user->total_points,
            ],
            'tier_info' => $tierInfo,
            'streak' => [
                'current_streak'        => (int) $streak->current_streak,
                'longest_streak'        => (int) $streak->longest_streak,
                'is_streak_active_today'=> ($streak->last_activity_date?->toDateString() === $today),
            ],
            'hydration' => [
                'current_glasses'    => $currentGlasses,
                'target_glasses'     => 8,
                'current_ml'         => $currentGlasses * 250,
                'target_ml'          => 2000,
                'is_completed_today' => $isHydrationComplete,
            ],
            'tasks'                 => $formattedTasks,
            'unlocked_badges_count' => $unlockedBadgesCount,
        ];
    }
}
