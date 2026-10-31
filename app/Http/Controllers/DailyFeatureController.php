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

    public function updateCategory(Request $request)
    {
        $request->validate(['category' => 'required|string']);

        $user = auth()->user();
        $user->category = $request->category;
        $user->save();

        return response()->json([
            'message'  => 'Category updated successfully',
            'category' => $user->category,
        ]);
    }

    public function getProgress(Request $request)
    {
        $user  = auth()->user();
        $today = Carbon::today()->toDateString();

        $tasksQuery = DailyTask::whereNull('target_category');
        if ($user->category) {
            $tasksQuery->orWhere('target_category', $user->category);
        }
        $tasks = $tasksQuery->get();

        $completedLogs = UserTaskLog::where('user_id', $user->user_id)
            ->whereDate('completed_date', $today)
            ->pluck('daily_task_id')
            ->toArray();

        $formattedTasks = $tasks->map(fn($task) => [
            'id'           => $task->id,
            'title'        => $task->title,
            'description'  => $task->description,
            'task_type'    => $task->task_type,
            'points'       => $task->points,
            'is_completed' => in_array($task->id, $completedLogs),
            'is_auto'      => str_starts_with($task->task_type, 'feature_'),
        ]);

        $streak = $this->incentiveService->getOrCreateStreak($user);
        $wallet = $this->incentiveService->getOrCreateWallet($user);

        return response()->json([
            'tasks'          => $formattedTasks,
            'streak'         => $streak->current_streak,
            'longest_streak' => $streak->longest_streak,
            'wallet_dinkes'  => (float) $wallet->wallet_dinkes,
            'wallet_ad'      => (float) $wallet->wallet_ad,
            'total_balance'  => (float) ($wallet->wallet_dinkes + $wallet->wallet_ad),
        ]);
    }

    public function completeTask(Request $request)
    {
        $request->validate([
            'daily_task_id' => 'required|exists:daily_tasks,id',
        ]);

        return $this->logTaskCompletion(auth()->user(), (int) $request->daily_task_id);
    }

    /**
     * Auto-track feature access by task_type slug.
     * Called from mobile when user navigates to a feature.
     * Idempotent: safe to call multiple times in the same day.
     */
    public function trackFeature(Request $request)
    {
        $request->validate([
            'feature' => 'required|string',
        ]);

        $taskType = 'feature_' . $request->feature;
        $task     = DailyTask::where('task_type', $taskType)->first();

        if (!$task) {
            return response()->json(['message' => 'Feature task not found', 'tracked' => false], 404);
        }

        return $this->logTaskCompletion(auth()->user(), $task->id);
    }

    /**
     * Verifikasi Piring Gizi Seimbang (Isi Piringku Kemenkes)
     * POST /api/user/daily-task/verify-food
     */
    public function verifyFoodPlate(Request $request)
    {
        $request->validate([
            'compliance_score' => 'required|numeric|min:0|max:1',
            'photo_url'        => 'nullable|string',
            'food_items'       => 'nullable|array',
        ]);

        $user = auth()->user();
        $result = $this->incentiveService->verifyAndRewardFood(
            $user,
            (float) $request->compliance_score,
            $request->photo_url,
            $request->food_items ?? []
        );

        return response()->json($result);
    }

    /**
     * Wallet & Streak Summary for Mobile User
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
        $from = Carbon::now()->subDays($days)->toDateString();

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
        $today = Carbon::today()->toDateString();

        $existing = UserTaskLog::where('user_id', $user->user_id)
            ->where('daily_task_id', $taskId)
            ->whereDate('completed_date', $today)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Already tracked today',
                'tracked' => false,
            ]);
        }

        $task = DailyTask::find($taskId);
        $points = $task?->points ?? 10;
        $title = $task?->title ?? 'Tugas Harian';

        // Award points and advance streak atomically via IncentiveService
        $awardResult = $this->incentiveService->awardPoints(
            $user,
            (float) $points,
            'dinkes',
            'Penyelesaian Tugas: ' . $title,
            $taskId
        );

        return response()->json([
            'message'        => 'Tracked successfully',
            'tracked'        => true,
            'points_awarded' => $points,
            'current_streak' => $awardResult['current_streak'],
            'balance_dinkes' => $awardResult['balance_dinkes'],
        ]);
    }
}
