<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Support\AuthToken;

class AdminAccountController extends Controller
{
    public function createBidan(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        $v = Validator::make($request->all(), [
            'name'     => ['required','string','max:255'],
            'email'    => ['required','string','email','max:255','unique:users,email'],
            'password' => ['required','string','min:6'],
        ]);
        if ($v->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $v->errors(),
            ], 422);
        }

        $userId = DB::table('users')->insertGetId([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'role'       => 'bidan',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'user_id');

        return response()->json([
            'status'  => 'success',
            'message' => 'Akun bidan berhasil dibuat',
            'data'    => [
                'user_id' => $userId,
                'name'    => $request->name,
                'email'   => $request->email,
                'role'    => 'bidan',
                'is_active' => true,
            ],
        ], 201);
    }

    public function createDinkes(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        $v = Validator::make($request->all(), [
            'name'     => ['required','string','max:255'],
            'email'    => ['required','string','email','max:255','unique:users,email'],
            'password' => ['required','string','min:6'],
        ]);
        if ($v->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $v->errors(),
            ], 422);
        }

        $userId = DB::table('users')->insertGetId([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'role'       => 'dinkes',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'user_id');

        return response()->json([
            'status'  => 'success',
            'message' => 'Akun dinkes berhasil dibuat',
            'data'    => [
                'user_id' => $userId,
                'name'    => $request->name,
                'email'   => $request->email,
                'role'    => 'dinkes',
                'is_active' => true,
            ],
        ], 201);
    }

    public function reset(Request $request, int $userId): JsonResponse
    {
        [, $role] = AuthToken::uidRoleOrFail($request);
        if ($role !== 'admin') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: admin role required.',
            ], 401);
        }

        $v = Validator::make($request->all(), [
            'new_password' => ['required','string','min:6'],
        ]);
        if ($v->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $v->errors(),
            ], 422);
        }

        $rows = DB::table('users')
            ->where('user_id', $userId)
            ->update([
                'password'      => Hash::make($request->new_password),
                'token_version' => DB::raw('token_version + 1'),
                'updated_at'    => now(),
            ]);

        if ($rows === 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Password has been reset. All previous tokens are revoked.',
        ]);
    }

    protected function assertAdmin(Request $request): void
    {
        $this->assertRole($request, 'admin');
    }

    protected function assertRole(Request $request, string $requiredRole): void
    {
        try {
            $token = JWTAuth::getToken();
            if (!$token) {
                $authHeader = $request->header('Authorization') ?: $request->server('HTTP_AUTHORIZATION');
                if ($authHeader && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
                    $token = $m[1];
                }
            }
            if (!$token) {
                abort(response()->json([
                    'status'  => 'error',
                    'message' => 'Missing Authorization: Bearer <token> header.',
                ], 401));
            }

            $payload = JWTAuth::setToken($token)->getPayload();
            $role = strtolower((string) $payload->get('role'));

            if ($role !== strtolower($requiredRole)) {
                abort(response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthorized',
                ], 401));
            }

        } catch (TokenExpiredException $e) {
            abort(response()->json(['status'=>'error','message'=>'Token has expired.'], 401));
        } catch (TokenInvalidException $e) {
            abort(response()->json(['status'=>'error','message'=>'Token is invalid.'], 401));
        } catch (JWTException $e) {
            abort(response()->json(['status'=>'error','message'=>'Unable to parse token.'], 401));
        } catch (\Throwable $e) {
            abort(response()->json(['status'=>'error','message'=>'Invalid or missing token.'], 401));
        }
    }

    public function allUser(Request $request): JsonResponse
    {
        try {
            $token = JWTAuth::getToken();
            if (! $token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Missing Authorization token.'
                ], 401);
            }

            $payload = JWTAuth::setToken($token)->getPayload();
            $role = $payload->get('role');

            if ($role !== 'admin') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized: admin role required.'
                ], 403);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid token: '.$e->getMessage(),
            ], 401);
        }

        $filterRole = $request->query('role');

        $query = DB::table('users')
            ->select('user_id', 'name', 'email', 'role', 'is_active', 'created_at', 'updated_at')
            ->orderBy('created_at', 'desc');

        if ($filterRole) {
            $query->where('role', strtolower($filterRole));
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            return response()->json([
                'status'  => 'success',
                'message' => $filterRole 
                    ? "No users found for role '{$filterRole}'."
                    : 'No users found.',
                'data'    => [],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => $filterRole
                ? "All users with role '{$filterRole}' retrieved successfully."
                : 'All users retrieved successfully.',
            'data' => $users,
        ]);
    }

    public function bidanIbuHamil(Request $request): JsonResponse
    {
        $this->assertRole($request, 'bidan');

        $search = $request->query('search');

        $query = DB::table('users')
            ->leftJoin('user_profile', 'user_profile.user_id', '=', 'users.user_id')
            ->select([
                'users.user_id',
                'users.name',
                'users.email',
                'users.role',
                'users.is_active',
                'users.created_at',
                'users.updated_at',
                'user_profile.tanggal_lahir',
                'user_profile.usia',
                'user_profile.alamat',
                'user_profile.no_telepon',
                'user_profile.pendidikan_terakhir',
                'user_profile.pekerjaan',
                'user_profile.golongan_darah',
                'user_profile.photo',
            ])
            ->where('users.role', 'ibu_hamil')
            ->orderBy('users.created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $ibuHamil = $query->get()->map(function ($row) {
            $row->photo_url = \App\Helpers\PhotoHelper::transformPhotoUrl($row->photo, 'public');
            return $row;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar ibu hamil berhasil diambil.',
            'data'    => $ibuHamil,
        ]);
    }
}
