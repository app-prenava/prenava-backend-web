<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HealthScanHistory;
use Illuminate\Http\JsonResponse;

class HealthHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $history = HealthScanHistory::where('user_id', $user->user_id ?? $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $history,
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $history = HealthScanHistory::where('user_id', $user->user_id ?? $user->id)->find($id);

        if (!$history) {
            return response()->json([
                'status'  => 'error',
                'message' => 'History not found.',
            ], 404);
        }

        $history->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'History deleted successfully.',
        ]);
    }
}
