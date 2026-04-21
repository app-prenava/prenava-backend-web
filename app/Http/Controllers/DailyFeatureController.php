<?php

namespace App\Http\Controllers;

use App\Models\DailyTask;
use App\Models\UserTaskLog;
use App\Models\UserStreak;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DailyFeatureController extends Controller
{
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

        $streak = UserStreak::firstOrCreate(
            ['user_id' => $user->user_id],
            ['current_streak' => 0, 'longest_streak' => 0]
        );

        return response()->json([
            'tasks'          => $formattedTasks,
            'streak'         => $streak->current_streak,
            'longest_streak' => $streak->longest_streak,
        ]);
    }

    public function completeTask(Request $request)
    {
        $request->validate([
            'daily_task_id' => 'required|exists:daily_tasks,id',
        ]);

        return $this->logTaskCompletion(auth()->user(), $request->daily_task_id);
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
     * Analytics: feature usage summary (for Tugas Akhir research).
     * Returns how many times each feature was accessed across all users,
     * grouped by task_type and date range.
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
        $today     = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        $existing = UserTaskLog::where('user_id', $user->user_id)
            ->where('daily_task_id', $taskId)
            ->whereDate('completed_date', $today)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Already tracked today', 'tracked' => false]);
        }

        UserTaskLog::create([
            'user_id'        => $user->user_id,
            'daily_task_id'  => $taskId,
            'completed_date' => $today,
        ]);

        $streak = UserStreak::firstOrCreate(
            ['user_id' => $user->user_id],
            ['current_streak' => 0, 'longest_streak' => 0]
        );

        if ($streak->last_activity_date !== $today) {
            $streak->current_streak = ($streak->last_activity_date === $yesterday)
                ? $streak->current_streak + 1
                : 1;

            if ($streak->current_streak > $streak->longest_streak) {
                $streak->longest_streak = $streak->current_streak;
            }

            $streak->last_activity_date = $today;
            $streak->save();
        }

        return response()->json([
            'message'       => 'Tracked successfully',
            'tracked'       => true,
            'points_awarded' => DailyTask::find($taskId)?->points ?? 0,
        ]);
    }
}
