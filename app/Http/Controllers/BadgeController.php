<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\DailyTask;
use App\Models\FoodScan;
use App\Models\UserBadge;
use App\Models\UserTaskLog;
use App\Models\WaterIntake;
use App\Services\IncentiveService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    protected IncentiveService $incentiveService;

    public function __construct(IncentiveService $incentiveService)
    {
        $this->incentiveService = $incentiveService;
    }

    /**
     * Get all badges with unlocked status and quantitative progress for authenticated user
     * GET /api/user/badges
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Jalankan evaluasi badge terlebih dahulu untuk memastikan jika ada lencana baru yang berhak didapat
        $newlyUnlocked = $this->incentiveService->evaluateBadges($user);

        $activeBadges = Badge::where('is_active', true)->get();
        $unlockedBadges = UserBadge::where('user_id', $user->user_id)
            ->get()
            ->keyBy('badge_id');

        $streak = $this->incentiveService->getOrCreateStreak($user);

        // Precompute counts for badges progress
        $ttdTaskId = DailyTask::where('task_type', 'supplement')->value('id');
        $ttdCount = $ttdTaskId
            ? UserTaskLog::where('user_id', $user->user_id)->where('daily_task_id', $ttdTaskId)->count()
            : 0;

        $hydrationDays = WaterIntake::where('user_id', $user->user_id)
            ->where('is_target_reached', true)
            ->count();

        $foodTaskId = DailyTask::where('task_type', 'food_compliance')->value('id');
        $foodTaskCount = $foodTaskId
            ? UserTaskLog::where('user_id', $user->user_id)->where('daily_task_id', $foodTaskId)->count()
            : 0;
        $foodScanCount = FoodScan::where('user_id', $user->user_id)
            ->where('nutrition_score', '>=', 70)
            ->count();
        $foodCount = max($foodTaskCount, $foodScanCount);

        $exerciseTaskId = DailyTask::where('task_type', 'exercise')->value('id');
        $exerciseCount = $exerciseTaskId
            ? UserTaskLog::where('user_id', $user->user_id)->where('daily_task_id', $exerciseTaskId)->count()
            : 0;

        $today = $this->incentiveService->getToday();
        $earlyBirdCount = UserTaskLog::where('user_id', $user->user_id)
            ->whereDate('completed_date', $today)
            ->count();

        $formattedBadges = $activeBadges->map(function ($badge) use (
            $unlockedBadges,
            $streak,
            $ttdCount,
            $hydrationDays,
            $foodCount,
            $exerciseCount,
            $earlyBirdCount
        ) {
            $isUnlocked = isset($unlockedBadges[$badge->id]);
            $unlockedAt = $isUnlocked ? $unlockedBadges[$badge->id]->unlocked_at?->toIso8601String() : null;

            $progressCurrent = 0;
            switch ($badge->requirement_type) {
                case 'streak_days':
                    $progressCurrent = max($streak->current_streak, $streak->longest_streak);
                    break;
                case 'ttd_count':
                    $progressCurrent = $ttdCount;
                    break;
                case 'hydration_streak':
                    $progressCurrent = $hydrationDays;
                    break;
                case 'food_compliance_count':
                    $progressCurrent = $foodCount;
                    break;
                case 'exercise_count':
                    $progressCurrent = $exerciseCount;
                    break;
                case 'early_bird':
                    $progressCurrent = min(1, $earlyBirdCount);
                    break;
                default:
                    $progressCurrent = $isUnlocked ? $badge->requirement_threshold : 0;
                    break;
            }

            return [
                'id'                   => $badge->id,
                'badge_code'           => $badge->badge_code,
                'name'                 => $badge->name,
                'category'             => $badge->category,
                'description'          => $badge->description,
                'icon'                 => $badge->icon,
                'bonus_points'         => (int) $badge->bonus_points,
                'is_unlocked'          => $isUnlocked,
                'unlocked_at'          => $unlockedAt,
                'progress_current'     => min($progressCurrent, $badge->requirement_threshold),
                'progress_target'      => (int) $badge->requirement_threshold,
                'requirement_type'     => $badge->requirement_type,
            ];
        });

        return response()->json([
            'status'         => 'success',
            'badges'         => $formattedBadges,
            'total_unlocked' => count($unlockedBadges),
            'total_badges'   => $activeBadges->count(),
            'newly_unlocked' => $newlyUnlocked,
        ]);
    }
}
