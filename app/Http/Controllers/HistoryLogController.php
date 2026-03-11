<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ActivityLog;
use App\Support\AuthToken;

class HistoryLogController extends Controller
{
    /**
     * GET /api/admin/history-log
     *
     * Returns paginated history logs for the admin panel.
     *
     * Query Parameters:
     *   - page        : int    (default: 1)
     *   - per_page    : int    (default: 20, max: 100)
     *   - activity_type : string (filter by activity type, e.g. 'login', 'logout')
     *   - user_id     : int    (filter by specific user)
     *   - search      : string (search by user name or email)
     *   - start_date  : string (Y-m-d format, filter logs from this date)
     *   - end_date    : string (Y-m-d format, filter logs until this date)
     *   - sort_by     : string (column to sort by, default: 'created_at')
     *   - sort_order  : string ('asc' or 'desc', default: 'desc')
     */
    public function index(Request $request): JsonResponse
    {
        // Ensure only admin can access
        [, $role] = AuthToken::uidRoleOrFail($request);
        if ($role !== 'admin') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: admin role required.',
            ], 401);
        }

        $perPage   = min((int) $request->query('per_page', 20), 100);
        $sortBy    = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');

        // Validate sort columns
        $allowedSorts = ['created_at', 'activity_type', 'user_name', 'user_email'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        $query = ActivityLog::query();

        // Filter by activity type
        if ($request->filled('activity_type')) {
            $query->where('activity_type', $request->query('activity_type'));
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }

        // Search by name or email
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', '%' . $search . '%')
                  ->orWhere('user_email', 'like', '%' . $search . '%');
            });
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->query('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->query('end_date'));
        }

        // Order and paginate
        $logs = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        // Get available activity types for filter dropdown
        $activityTypes = collect(ActivityLog::LABELS)->map(function ($label, $type) {
            return ['value' => $type, 'label' => $label];
        })->values();

        return response()->json([
            'status'  => 'success',
            'message' => 'History log retrieved successfully.',
            'data'    => $logs->items(),
            'activity_types' => $activityTypes,
            'pagination' => [
                'current_page'  => $logs->currentPage(),
                'per_page'      => $logs->perPage(),
                'total'         => $logs->total(),
                'last_page'     => $logs->lastPage(),
                'from'          => $logs->firstItem(),
                'to'            => $logs->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/admin/history-log/summary
     *
     * Returns summary statistics of activity logs.
     */
    public function summary(Request $request): JsonResponse
    {
        [, $role] = AuthToken::uidRoleOrFail($request);
        if ($role !== 'admin') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: admin role required.',
            ], 401);
        }

        $totalLogs = ActivityLog::count();

        // Count per activity type
        $perType = ActivityLog::selectRaw('activity_type, COUNT(*) as total')
            ->groupBy('activity_type')
            ->pluck('total', 'activity_type')
            ->toArray();

        // Today's activity count
        $todayCount = ActivityLog::whereDate('created_at', today())->count();

        // This week's activity count
        $weekCount = ActivityLog::whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ])->count();

        // Recent 5 logs
        $recentLogs = ActivityLog::orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'total_logs'      => $totalLogs,
                'today_count'     => $todayCount,
                'this_week_count' => $weekCount,
                'per_activity_type' => $perType,
                'recent_logs'     => $recentLogs,
            ],
        ]);
    }

    /**
     * GET /api/admin/history-log/user/{userId}
     *
     * Returns all activity logs for a specific user.
     */
    public function userLogs(Request $request, int $userId): JsonResponse
    {
        [, $role] = AuthToken::uidRoleOrFail($request);
        if ($role !== 'admin') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: admin role required.',
            ], 401);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);

        $logs = ActivityLog::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'data'    => $logs->items(),
            'pagination' => [
                'current_page'  => $logs->currentPage(),
                'per_page'      => $logs->perPage(),
                'total'         => $logs->total(),
                'last_page'     => $logs->lastPage(),
                'from'          => $logs->firstItem(),
                'to'            => $logs->lastItem(),
            ],
        ]);
    }
}
