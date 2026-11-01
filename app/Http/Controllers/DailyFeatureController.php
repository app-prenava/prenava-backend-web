<?php

namespace App\Http\Controllers;

use App\Models\DailyTask;
use App\Models\UserTaskLog;
use App\Models\UserStreak;
use App\Services\IncentiveService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DailyFeatureController extends Controller
{
    protected IncentiveService $incentiveService;

    public function __construct(IncentiveService $incentiveService)
    {
        $this->incentiveService = $incentiveService;
    }

    /**
     * Update user pregnancy / status category manually (if desired)
     * PUT /api/user/category
     */
    public function updateCategory(Request $request)
    {
        $request->validate(['category' => 'required|string']);

        $user = auth()->user();
        $user->category = $request->category;
        $user->save();

        return response()->json([
            'message'  => 'Kategori berhasil diperbarui',
            'category' => $user->category,
        ]);
    }

    /**
     * Get complete daily progress, maternal tier, streak, progressive hydration, and tasks
     * GET /api/user/daily-progress
     */
    public function getProgress(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $summary = $this->incentiveService->getDailyProgressSummary($user);

        // Pertahankan juga field legacy agar backward-compatible dengan versi mobile lama
        $wallet = $this->incentiveService->getOrCreateWallet($user);

        return response()->json(array_merge($summary, [
            'wallet_dinkes'  => (float) $wallet->wallet_dinkes,
            'wallet_ad'      => (float) $wallet->wallet_ad,
            'total_balance'  => (float) ($wallet->wallet_dinkes + $wallet->wallet_ad),
        ]));
    }

    /**
     * Complete a manual daily task (e.g. Tablet Tambah Darah, Jalan Santai)
     * POST /api/user/daily-task/complete
     */
    public function completeTask(Request $request)
    {
        $request->validate([
            'daily_task_id' => 'required|exists:daily_tasks,id',
        ]);

        $user = auth()->user();
        return $this->logTaskCompletion($user, (int) $request->daily_task_id);
    }

    /**
     * Record 1 glass of water intake (250 ml) for today WIB.
     * Completes hydration task automatically when reaching 8 glasses.
     * POST /api/user/hydration/drink
     */
    public function drinkWater(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $result = $this->incentiveService->recordHydrationGlass($user);

        return response()->json([
            'status'            => 'success',
            'message'           => $result['is_completed_now']
                ? 'Luar biasa! Target hidrasi 8 gelas tercapai (+10 Poin Sehat).'
                : 'Berhasil mencatat 1 gelas air minum (+250 ml).',
            'current_glasses'   => $result['current_glasses'],
            'target_glasses'    => $result['target_glasses'],
            'current_ml'        => $result['current_ml'],
            'target_ml'         => $result['target_ml'],
            'is_target_reached' => $result['is_target_reached'],
            'points_earned'     => $result['points_earned'],
            'new_badges'        => $result['new_badges'],
        ]);
    }

    /**
     * Auto-track feature access by task_type slug (e.g. feature_anemia, feature_depresi).
     * Called from mobile when user navigates to a feature.
     * Idempotent: safe to call multiple times in the same day.
     * POST /api/user/feature-track OR POST /api/user/daily-task/track-feature
     */
    public function trackFeature(Request $request)
    {
        $request->validate([
            'feature' => 'required|string',
        ]);

        $rawFeature = $request->feature;
        $taskType = str_starts_with($rawFeature, 'feature_') ? $rawFeature : ('feature_' . $rawFeature);
        $task = DailyTask::where('task_type', $taskType)->first();

        if (!$task) {
            return response()->json([
                'message' => 'Fitur tugas tidak ditemukan',
                'tracked' => false,
            ], 404);
        }

        return $this->logTaskCompletion(auth()->user(), $task->id);
    }

    /**
     * Verifikasi Piring Gizi Seimbang (Isi Piringku Kemenkes)
     * POST /api/user/daily-task/verify-food OR POST /api/user/food-compliance
     */
    public function verifyFoodPlate(Request $request)
    {
        $request->validate([
            'compliance_score' => 'required|numeric|min:0|max:1',
            'food_scan_id'     => 'nullable|integer',
            'photo_url'        => 'nullable|string',
            'food_items'       => 'nullable|array',
        ]);

        $user = auth()->user();
        $result = $this->incentiveService->verifyAndRewardFood(
            $user,
            (float) $request->compliance_score,
            $request->photo_url,
            $request->food_items ?? [],
            $request->food_scan_id ? (int) $request->food_scan_id : null
        );

        return response()->json($result);
    }

    /**
     * Wallet & Streak Summary for Mobile User (Legacy compatibility)
     * GET /api/user/wallet/summary
     */
    public function getWalletSummary(Request $request)
    {
        $user = auth()->user();
        $summary = $this->incentiveService->getWalletSummary($user);

        return response()->json([
            'status' => 'success',
            'data'   => $summary,
        ]);
    }

    /**
     * Analytics: feature usage summary (for Tugas Akhir research).
     */
    public function featureAnalytics(Request $request)
    {
        $days = (int) $request->query('days', 30);
        $from = Carbon::now(IncentiveService::TIMEZONE)->subDays($days)->toDateString();

        $analytics = UserTaskLog::join('daily_tasks', 'daily_tasks.id', '=', 'user_task_logs.daily_task_id')
            ->where('daily_tasks.task_type', 'like', 'feature_%')
            ->where('user_task_logs.completed_date', '>=', $from)
            ->selectRaw('daily_tasks.task_type, daily_tasks.title, COUNT(*) as total_access, COUNT(DISTINCT user_task_logs.user_id) as unique_users')
            ->groupBy('daily_tasks.task_type', 'daily_tasks.title')
            ->orderByDesc('total_access')
            ->get();

        return response()->json([
            'period_days' => $days,
            'from'        => $from,
            'features'    => $analytics,
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function logTaskCompletion($user, int $taskId)
    {
        $today = $this->incentiveService->getToday();

        $existing = UserTaskLog::where('user_id', $user->user_id)
            ->where('daily_task_id', $taskId)
            ->whereDate('completed_date', $today)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Tugas sudah diselesaikan hari ini.',
                'tracked' => false,
            ]);
        }

        $task = DailyTask::find($taskId);
        if (!$task) {
            return response()->json([
                'message' => 'Tugas tidak ditemukan.',
                'tracked' => false,
            ], 404);
        }

        $points = $task->points ?? 10;
        $title = $task->title ?? 'Tugas Harian';

        // Award points, advance streak, and evaluate badges atomically
        $awardResult = $this->incentiveService->awardPoints(
            $user,
            (float) $points,
            'dinkes',
            'Penyelesaian Tugas: ' . $title,
            $taskId,
            'daily_task'
        );

        return response()->json([
            'message'        => 'Tugas berhasil diselesaikan!',
            'tracked'        => true,
            'points_awarded' => $points,
            'total_points'   => $awardResult['total_points'],
            'current_streak' => $awardResult['current_streak'],
            'tier_info'      => $awardResult['tier_info'],
            'new_badges'     => $awardResult['new_badges'],
        ]);
    }
}
