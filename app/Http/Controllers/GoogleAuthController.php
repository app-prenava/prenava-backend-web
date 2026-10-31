<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class GoogleAuthController extends Controller
{
    /**
     * Handle Google Sign-In from mobile app.
     * Flutter sends the Google id_token, backend verifies and creates/logs in user.
     */
    public function handleGoogleLogin(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'id_token' => ['required', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json($v->errors(), 422);
        }

        // Verify id_token with Google
        $googleUser = $this->verifyGoogleToken($request->id_token);

        if (!$googleUser) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token Google tidak valid.',
            ], 401);
        }

        $email    = $googleUser['email'];
        $googleId = $googleUser['sub'];
        $name     = $googleUser['name'] ?? explode('@', $email)[0];
        $picture  = $googleUser['picture'] ?? null;

        // Find existing user by google_id or email
        $user = User::where('google_id', $googleId)->first()
             ?? User::where('email', $email)->first();

        if ($user) {
            // Link Google account if not linked yet
            if (!$user->google_id) {
                $user->update([
                    'google_id'         => $googleId,
                    'auth_provider'     => $user->auth_provider === 'email' ? 'email' : 'google',
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            }

            if (!$user->is_active) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Akun dinonaktifkan. Hubungi admin.',
                ], 403);
            }

            // Auto-enrich user profile photo from Google if user has none
            if ($picture && $user->role === 'ibu_hamil') {
                $profile = $user->userProfile;
                if ($profile && empty($profile->photo)) {
                    $profile->update(['photo' => $picture]);
                } elseif (!$profile) {
                    $user->userProfile()->create(['photo' => $picture]);
                }
            }
        } else {
            // Create new user — Google users are auto-verified
            $user = User::create([
                'name'              => $name,
                'email'             => $email,
                'password'          => null,
                'role'              => 'ibu_hamil',
                'is_active'         => true,
                'google_id'         => $googleId,
                'auth_provider'     => 'google',
                'email_verified_at' => now(),
            ]);

            // Save Google profile picture to profile
            if ($picture) {
                $user->userProfile()->create([
                    'photo' => $picture,
                ]);
            }

            ActivityLogService::logFromUser(
                ActivityLog::TYPE_REGISTER,
                $user,
                "User {$user->name} registrasi via Google.",
                request: $request
            );
        }

        $token  = $this->issueToken($user);
        $ttlMap = config('auth_tokens.ttl_seconds');
        $ttlSec = $ttlMap[$user->role] ?? ($ttlMap['default'] ?? 0);

        ActivityLogService::logFromUser(
            ActivityLog::TYPE_LOGIN,
            $user,
            "User {$user->name} login via Google.",
            request: $request
        );

        return response()->json([
            'status' => 'success',
            'user'   => [
                'user_id' => $user->user_id,
                'name'    => $user->name,
                'email'   => $user->email,
                'role'    => $user->role,
                'photo'   => $user->userProfile?->photo ?? $picture,
            ],
            'authorization' => [
                'token'      => $token,
                'type'       => 'bearer',
                'expires_in' => $ttlSec ?: null,
            ],
        ]);
    }

    /**
     * Verify Google ID token via Google's tokeninfo endpoint.
     */
    private function verifyGoogleToken(string $idToken): ?array
    {
        try {
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            // Verify audience matches any of our allowed client IDs (web, android, ios)
            $allowedClientIds = config('services.google.allowed_client_ids', []);
            $singleClientId = config('services.google.client_id', config('services.google.android_client_id'));
            if (!empty($allowedClientIds)) {
                $aud = $data['aud'] ?? '';
                if (!in_array($aud, $allowedClientIds, true)) {
                    return null;
                }
            } elseif ($singleClientId && ($data['aud'] ?? '') !== $singleClientId) {
                return null;
            }

            // Must have verified email
            if (($data['email_verified'] ?? 'false') !== 'true') {
                return null;
            }

            return $data;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function issueToken(User $user): string
    {
        $claims = [
            'uid'  => $user->user_id,
            'role' => $user->role,
            'name' => $user->name,
            'email' => $user->email,
            'tv'   => (int) $user->token_version,
        ];

        $ttlMap = config('auth_tokens.ttl_seconds');
        $ttlSec = $ttlMap[$user->role] ?? ($ttlMap['default'] ?? (30 * 24 * 60 * 60));

        $factory = JWTAuth::factory();
        $ttlMinutes = ($ttlSec && $ttlSec > 0) ? (int) ($ttlSec / 60) : (30 * 24 * 60);
        $factory->setTTL($ttlMinutes);
        return JWTAuth::claims($claims)->fromUser($user);
    }
}
