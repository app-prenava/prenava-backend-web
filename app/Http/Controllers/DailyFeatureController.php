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
        $request->validate([
            'category' => 'required|string'
        ]);

        $user = auth()->user();
        $user->category = $request->category;
        $user->save();

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $user->category
        ]);
    }

    public function getProgress(Request $request)
    {
        $user = auth()->user();
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

        $formattedTasks = $tasks->map(function ($task) use ($completedLogs) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'task_type' => $task->task_type,
                'points' => $task->points,
                'is_completed' => in_array($task->id, $completedLogs)
            ];
        });

        $streak = UserStreak::firstOrCreate(
            ['user_id' => $user->user_id],
            ['current_streak' => 0, 'longest_streak' => 0]
        );

        return response()->json([
            'tasks' => $formattedTasks,
            'streak' => $streak->current_streak,
            'longest_streak' => $streak->longest_streak
        ]);
    }

    public function completeTask(Request $request)
    {
        $request->validate([
            'daily_task_id' => 'required|exists:daily_tasks,id'
        ]);

        $user = auth()->user();
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        $existing = UserTaskLog::where('user_id', $user->user_id)
            ->where('daily_task_id', $request->daily_task_id)
            ->whereDate('completed_date', $today)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Task already completed today'], 400);
        }

        UserTaskLog::create([
            'user_id' => $user->user_id,
            'daily_task_id' => $request->daily_task_id,
            'completed_date' => $today
        ]);

        $streak = UserStreak::firstOrCreate(
            ['user_id' => $user->user_id],
            ['current_streak' => 0, 'longest_streak' => 0]
        );

        if ($streak->last_activity_date !== $today) {
            if ($streak->last_activity_date === $yesterday) {
                $streak->current_streak += 1;
            } else {
                $streak->current_streak = 1; 
            }

            if ($streak->current_streak > $streak->longest_streak) {
                $streak->longest_streak = $streak->current_streak;
            }

            $streak->last_activity_date = $today;
            $streak->save();
        }

        return response()->json([
            'message' => 'Task completed successfully',
            'points_awarded' => DailyTask::find($request->daily_task_id)->points
        ]);
    }
}
