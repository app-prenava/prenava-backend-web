<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HealthScanHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class HealthHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $history = HealthScanHistory::where('user_id', $user->user_id ?? $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data'   => $history,
            ]);
        } catch (QueryException $e) {
            Log::error('Health history query failed.', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Riwayat belum tersedia.',
            ], 503);
        } catch (\Throwable $e) {
            Log::error('Unexpected error while fetching health history.', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan server.',
            ], 500);
        }
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
