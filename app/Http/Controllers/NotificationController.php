<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserNotification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->resolveAuthenticatedUser();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $userId = $user->user_id ?? $user->getKey();

        $limit = (int) $request->query('limit', 20);
        $page  = (int) $request->query('page', 1);

        $notifications = UserNotification::where('user_id', (string) $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        $unreadCount = UserNotification::where('user_id', (string) $userId)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'status'       => 'success',
            'unread_count' => $unreadCount,
            'data'         => $notifications->items(),
            'pagination'   => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
            ],
        ]);
    }

    public function markAsRead($id)
    {
        $user = $this->resolveAuthenticatedUser();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $userId = $user->user_id ?? $user->getKey();

        $notification = UserNotification::where('id', $id)
            ->where('user_id', (string) $userId)
            ->first();

        if (!$notification) {
            return response()->json(['status' => 'error', 'message' => 'Notifikasi tidak ditemukan.'], 404);
        }

        $notification->update(['is_read' => true]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Notifikasi ditandai telah dibaca.',
        ]);
    }

    public function markAllAsRead()
    {
        $user = $this->resolveAuthenticatedUser();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $userId = $user->user_id ?? $user->getKey();

        UserNotification::where('user_id', (string) $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Semua notifikasi ditandai telah dibaca.',
        ]);
    }

    private function resolveAuthenticatedUser(): ?User
    {
        try {
            if ($tokenUser = JWTAuth::parseToken()->authenticate()) {
                return $tokenUser;
            }
        } catch (\Throwable $th) {
        }

        return Auth::guard('api')->user();
    }
}
