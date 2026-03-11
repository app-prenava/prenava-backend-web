<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Support\AuthToken;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;


class AdminUserStatusController extends Controller
{
    public function deactivate(Request $request, int $userId): JsonResponse
    {
        [, $role] = AuthToken::uidRoleOrFail($request);
        if ($role !== 'admin') {
            return response()->json(['status'=>'error','message'=>'Unauthorized: admin role required.'], 401);
        }

        $updated = DB::table('users')
            ->where('user_id', $userId)
            ->update([
                'is_active'     => false,
                'token_version' => DB::raw('token_version + 1'),
                'updated_at'    => now(),
            ]);

        if (!$updated) {
            return response()->json(['status'=>'error','message'=>'User found.'], 404);
        }

        // Log deactivation
        $adminUser = User::find($request->user()->user_id ?? $request->user()->id);
        $affectedUser = User::find($userId);
        ActivityLogService::logFromUser(
            ActivityLog::TYPE_DEACTIVATED,
            $adminUser,
            "Admin {$adminUser->name} menonaktifkan akun user: " . ($affectedUser->name ?? $userId),
            ['affected_user_id' => $userId],
            $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'User deactivated and all tokens revoked.',
        ]);
    }

    public function activate(Request $request, int $userId): JsonResponse
    {
        [, $role] = AuthToken::uidRoleOrFail($request);
        if ($role !== 'admin') {
            return response()->json(['status'=>'error','message'=>'Unauthorized: admin role required.'], 401);
        }

        $updated = DB::table('users')
            ->where('user_id', $userId)
            ->update([
                'is_active'     => true,
                'token_version' => DB::raw('token_version + 1'),
                'updated_at'    => now(),
            ]);

        if (!$updated) {
            return response()->json(['status'=>'error','message'=>'User found.'], 404);
        }

        // Log activation
        $adminUser = User::find($request->user()->user_id ?? $request->user()->id);
        $affectedUser = User::find($userId);
        ActivityLogService::logFromUser(
            ActivityLog::TYPE_ACTIVATED,
            $adminUser,
            "Admin {$adminUser->name} mengaktifkan kembali akun user: " . ($affectedUser->name ?? $userId),
            ['affected_user_id' => $userId],
            $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'User activated. Old tokens revoked.',
        ]);
    }
}
