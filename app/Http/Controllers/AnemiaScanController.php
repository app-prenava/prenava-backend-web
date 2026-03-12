<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class AnemiaScanController extends Controller
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

        $file = $request->file('file');

        $mlUrl = rtrim(config('services.ml.anemia', 'http://72.61.213.163:9001/'), '/') . '/predict';

        try {
            $response = Http::timeout(30)
                ->retry(2, 500)
                ->attach(
                    'file',
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
