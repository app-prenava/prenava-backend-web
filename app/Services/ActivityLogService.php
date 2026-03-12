<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * ActivityLogService
 * 
 * Central service for recording user activities throughout the application.
 * Integrated with the following features and controllers:
 * - Auth: login, logout, register, change_password (AuthController)
 * - Profile: update profiles for all user roles (AddProfileController)
 * - Admin: account activation/deactivation (AdminUserStatusController)
 * - Diagnostics: depression detection (PrediksiDepresiController) and maternal health/anemia (DeteksiController)
 * - Recommendations: food (RekomendasiMakananController) and sports/exercise (RecomendationSportController)
 * - Account Dashboard: provides 'last_activity' labels (AdminAccountController)
 */
class ActivityLogService
{
    /**
     * Record an activity log entry.
     *
     * @param string      $activityType  One of ActivityLog::TYPE_* constants
     * @param int|null    $userId        User performing the action
     * @param string|null $userName      User's name
     * @param string|null $userEmail     User's email
     * @param string|null $userRole      User's role
     * @param string|null $description   Human-readable description
     * @param array|null  $metadata      Additional contextual data
     * @param Request|null $request      HTTP request (to extract IP and User-Agent)
     */
    public static function log(
        string  $activityType,
        ?int    $userId = null,
        ?string $userName = null,
        ?string $userEmail = null,
        ?string $userRole = null,
        ?string $description = null,
        ?array  $metadata = null,
        ?Request $request = null
    ): ActivityLog {
        $label = ActivityLog::LABELS[$activityType] ?? $activityType;

        return ActivityLog::create([
            'user_id'       => $userId,
            'user_name'     => $userName,
            'user_email'    => $userEmail,
            'user_role'     => $userRole,
            'activity_type' => $activityType,
            'activity_label'=> $label,
            'description'   => $description,
            'metadata'      => $metadata,
            'ip_address'    => $request?->ip(),
            'user_agent'    => $request?->userAgent(),
        ]);
    }

    /**
     * Shortcut: log from a request where user info is already known.
     */
    public static function logFromUser(
        string  $activityType,
        object  $user,
        ?string $description = null,
        ?array  $metadata = null,
        ?Request $request = null
    ): ActivityLog {
        return self::log(
            activityType: $activityType,
            userId:       $user->user_id ?? $user->id ?? null,
            userName:     $user->name ?? null,
            userEmail:    $user->email ?? null,
            userRole:     $user->role ?? null,
            description:  $description,
            metadata:     $metadata,
            request:      $request,
        );
    }
}
