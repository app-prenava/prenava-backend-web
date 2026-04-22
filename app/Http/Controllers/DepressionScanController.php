<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;

class DepressionScanController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,bmp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $file = $request->file('image');

        $mlUrl = rtrim(config('services.ml.depression'), '/') . '/predict';

        try {
            $response = Http::timeout(30)
                ->retry(2, 500)
                ->attach(
                    'image',
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName()
                )
                ->post($mlUrl);

            if (! $response->ok()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'ML service returned an error.',
                    'upstream_status' => $response->status(),
                ], 502);
            }

            $ml = $response->json();

            // Log deteksi depresi (scan wajah)
            if ($user = $request->user()) {
                // Save to History table
                \App\Models\HealthScanHistory::create([
                    'user_id' => $user->id,
                    'type'    => 'depression',
                    'result'  => $ml,
                ]);

                ActivityLogService::logFromUser(
                    ActivityLog::TYPE_DETEKSI_DEPRESI,
                    $user,
                    "User {$user->name} melakukan deteksi depresi melalui scan wajah.",
                    ['prediction' => $ml],
                    $request
                );
            }

            return response()->json([
                'status' => 'success',
                'data'   => $ml,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to connect to ML service.',
                'error_detail' => $e->getMessage(),
            ], 502);
        }
    }
}
