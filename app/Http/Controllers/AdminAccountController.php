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
use Illuminate\Support\Facades\Schema;
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
        $this->assertAnyRole($request, [$requiredRole]);
    }

    protected function assertAnyRole(Request $request, array $allowedRoles): void
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
            $normalizedAllowed = array_map(fn ($r) => strtolower(trim($r)), $allowedRoles);

            if (!in_array($role, $normalizedAllowed, true)) {
                abort(response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthorized',
                ], 403));
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
        $this->assertAnyRole($request, ['admin', 'dinkes']);

        $filterRole = $request->query('role');

        $query = DB::table('users')
            ->leftJoin('bidan_profile', 'bidan_profile.user_id', '=', 'users.user_id')
            ->leftJoin('user_dinkes', 'user_dinkes.user_id', '=', 'users.user_id')
            ->leftJoin('user_profile', 'user_profile.user_id', '=', 'users.user_id')
            ->select([
                'users.user_id',
                'users.name',
                'users.email',
                'users.role',
                'users.is_active',
                'users.created_at',
                'users.updated_at',
                'bidan_profile.tempat_praktik',
                'bidan_profile.alamat_praktik',
                'bidan_profile.kota_tempat_praktik',
                'bidan_profile.kecamatan_tempat_praktik',
                'bidan_profile.telepon_tempat_praktik',
                'bidan_profile.spesialisasi',
                'bidan_profile.photo as bidan_photo',
                'user_dinkes.jabatan',
                'user_dinkes.nip',
                'user_dinkes.photo as dinkes_photo',
                'user_profile.alamat as ibu_alamat',
                'user_profile.kota as ibu_kota',
                'user_profile.kecamatan as ibu_kecamatan',
                'user_profile.no_telepon as ibu_telepon',
                'user_profile.usia as ibu_usia',
                'user_profile.golongan_darah',
                DB::raw('(SELECT activity_label FROM activity_logs WHERE activity_logs.user_id = users.user_id ORDER BY created_at DESC LIMIT 1) as last_activity_label'),
                DB::raw('(SELECT activity_label FROM activity_logs WHERE activity_logs.user_id = users.user_id ORDER BY created_at DESC LIMIT 1) as last_activity'),
                DB::raw('(SELECT activity_type FROM activity_logs WHERE activity_logs.user_id = users.user_id ORDER BY created_at DESC LIMIT 1) as last_activity_type')
            ])
            ->orderBy('users.created_at', 'desc');

        if ($filterRole) {
            $query->where('users.role', strtolower($filterRole));
        }

        $users = $query->get()->map(function ($row) {
            if ($row->role === 'bidan') {
                $row->alamat = $row->alamat_praktik;
                $row->kota = $row->kota_tempat_praktik;
                $row->kecamatan = $row->kecamatan_tempat_praktik;
                $row->no_telepon = $row->telepon_tempat_praktik;
                $row->profile = [
                    'tempat_praktik' => $row->tempat_praktik,
                    'alamat_praktik' => $row->alamat_praktik,
                    'kota_tempat_praktik' => $row->kota_tempat_praktik,
                    'kecamatan_tempat_praktik' => $row->kecamatan_tempat_praktik,
                    'telepon_tempat_praktik' => $row->telepon_tempat_praktik,
                    'spesialisasi' => $row->spesialisasi,
                    'photo' => $row->bidan_photo ? asset('storage/' . $row->bidan_photo) : null,
                ];
            } elseif ($row->role === 'dinkes') {
                $row->profile = [
                    'jabatan' => $row->jabatan,
                    'nip' => $row->nip,
                    'photo' => $row->dinkes_photo ? asset('storage/' . $row->dinkes_photo) : null,
                ];
            } else {
                $row->alamat = $row->ibu_alamat;
                $row->kota = $row->ibu_kota;
                $row->kecamatan = $row->ibu_kecamatan;
                $row->no_telepon = $row->ibu_telepon;
                $row->profile = [
                    'alamat' => $row->ibu_alamat,
                    'kota' => $row->ibu_kota,
                    'kecamatan' => $row->ibu_kecamatan,
                    'no_telepon' => $row->ibu_telepon,
                    'usia' => $row->ibu_usia,
                    'golongan_darah' => $row->golongan_darah,
                ];
            }

            return $row;
        });

        return response()->json([
            'status' => 'success',
            'message' => $filterRole
                ? "All users with role '{$filterRole}' retrieved successfully."
                : 'All users retrieved successfully.',
            'data' => $users,
        ]);
    }

    public function adminGetBidanProfile(Request $request, int $userId): JsonResponse
    {
        $this->assertAnyRole($request, ['admin', 'dinkes']);

        $profile = DB::table('bidan_profile')->where('user_id', $userId)->first();
        if (!$profile) {
            return response()->json([
                'status' => 'success',
                'message' => 'Profil bidan belum dilengkapi.',
                'data' => null,
            ]);
        }

        $profile->photo = $profile->photo ? asset('storage/' . $profile->photo) : null;

        return response()->json([
            'status' => 'success',
            'message' => 'Profil bidan berhasil diambil.',
            'data' => $profile,
        ]);
    }

    public function adminGetDinkesProfile(Request $request, int $userId): JsonResponse
    {
        $this->assertAnyRole($request, ['admin', 'dinkes']);

        $profile = DB::table('user_dinkes')->where('user_id', $userId)->first();
        if (!$profile) {
            return response()->json([
                'status' => 'success',
                'message' => 'Profil dinkes belum dilengkapi.',
                'data' => null,
            ]);
        }

        $profile->photo = $profile->photo ? asset('storage/' . $profile->photo) : null;

        return response()->json([
            'status' => 'success',
            'message' => 'Profil dinkes berhasil diambil.',
            'data' => $profile,
        ]);
    }

    public function adminUpdateUser(Request $request, int $userId): JsonResponse
    {
        $this->assertAdmin($request);

        $user = DB::table('users')->where('user_id', $userId)->first();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengguna tidak ditemukan.',
            ], 404);
        }

        $v = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,' . $userId . ',user_id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal.',
                'errors' => $v->errors(),
            ], 422);
        }

        $userUpdate = ['updated_at' => now()];
        if ($request->has('name')) {
            $userUpdate['name'] = $request->name;
        }
        if ($request->has('email')) {
            $userUpdate['email'] = $request->email;
        }
        if ($request->has('is_active')) {
            $userUpdate['is_active'] = $request->boolean('is_active');
        }

        DB::table('users')->where('user_id', $userId)->update($userUpdate);

        if ($user->role === 'bidan') {
            $bidanFields = array_filter($request->only([
                'tempat_praktik',
                'alamat_praktik',
                'kota_tempat_praktik',
                'kecamatan_tempat_praktik',
                'telepon_tempat_praktik',
                'spesialisasi',
            ]), fn ($val) => $val !== null);

            if (!empty($bidanFields)) {
                $bidanFields['updated_at'] = now();
                $exists = DB::table('bidan_profile')->where('user_id', $userId)->exists();
                if ($exists) {
                    DB::table('bidan_profile')->where('user_id', $userId)->update($bidanFields);
                } else {
                    $bidanFields['user_id'] = $userId;
                    $bidanFields['tempat_praktik'] = $bidanFields['tempat_praktik'] ?? '-';
                    $bidanFields['alamat_praktik'] = $bidanFields['alamat_praktik'] ?? '-';
                    $bidanFields['kota_tempat_praktik'] = $bidanFields['kota_tempat_praktik'] ?? '-';
                    $bidanFields['kecamatan_tempat_praktik'] = $bidanFields['kecamatan_tempat_praktik'] ?? '-';
                    $bidanFields['created_at'] = now();
                    DB::table('bidan_profile')->insert($bidanFields);
                }
            }
        } elseif ($user->role === 'dinkes') {
            $dinkesFields = array_filter($request->only(['jabatan', 'nip']), fn ($val) => $val !== null);
            if (!empty($dinkesFields)) {
                $dinkesFields['updated_at'] = now();
                $exists = DB::table('user_dinkes')->where('user_id', $userId)->exists();
                if ($exists) {
                    DB::table('user_dinkes')->where('user_id', $userId)->update($dinkesFields);
                } else {
                    $dinkesFields['user_id'] = $userId;
                    $dinkesFields['jabatan'] = $dinkesFields['jabatan'] ?? '-';
                    $dinkesFields['nip'] = $dinkesFields['nip'] ?? '-';
                    $dinkesFields['created_at'] = now();
                    DB::table('user_dinkes')->insert($dinkesFields);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data pengguna berhasil diperbarui.',
        ]);
    }

    public function adminDeleteUser(Request $request, int $userId): JsonResponse
    {
        $this->assertAdmin($request);

        $user = DB::table('users')->where('user_id', $userId)->first();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengguna tidak ditemukan.',
            ], 404);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun administrator utama tidak dapat dihapus.',
            ], 403);
        }

        DB::beginTransaction();
        try {
            DB::table('bidan_profile')->where('user_id', $userId)->delete();
            DB::table('user_dinkes')->where('user_id', $userId)->delete();
            DB::table('user_profile')->where('user_id', $userId)->delete();
            DB::table('users')->where('user_id', $userId)->delete();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Akun pengguna berhasil dihapus.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            // Fallback: nonaktifkan jika terikat foreign key transaksi klinis
            DB::table('users')->where('user_id', $userId)->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Akun memiliki riwayat klinis terikat dan telah dinonaktifkan.',
            ]);
        }
    }

    protected function normalizeAnemiaScanRow(object $item): object
    {
        if (isset($item->result)) {
            $decoded = is_string($item->result) ? json_decode($item->result, true) : (array) $item->result;
            if (is_array($decoded)) {
                $label = $decoded['prediction_label']
                    ?? $decoded['diagnosis']
                    ?? $decoded['status']
                    ?? $decoded['hasil']
                    ?? ($decoded['is_anemic'] ?? false ? 'Terindikasi Anemia' : 'Normal');
                $riskLevel = $decoded['risk_level'] ?? $decoded['severity'] ?? null;
                $hb = $decoded['hb_estimate'] ?? $decoded['hemoglobin'] ?? null;
                $conf = $decoded['confidence_score'] ?? $decoded['confidence'] ?? null;

                $item->prediction_label = $label;
                $item->risk_level = $riskLevel;
                $item->hb_estimate = $hb;
                $item->confidence_score = $conf;
                $item->hasil_mata_kiri = $item->hasil_mata_kiri
                    ?? $decoded['hasil_mata_kiri']
                    ?? ($hb ? "{$label} (Est. Hb {$hb} g/dL)" : $label);
                $item->hasil_mata_kanan = $item->hasil_mata_kanan
                    ?? $decoded['hasil_mata_kanan']
                    ?? ($riskLevel ? "Risiko: " . ucfirst((string) $riskLevel) : $label);
            }
        }
        return $item;
    }

    protected function normalizeDepressionScanRow(object $item): object
    {
        if (isset($item->result)) {
            $decoded = is_string($item->result) ? json_decode($item->result, true) : (array) $item->result;
            if (is_array($decoded)) {
                $level = $decoded['depression_level']
                    ?? $decoded['risk_level']
                    ?? $decoded['risk_category']
                    ?? $decoded['prediction_label']
                    ?? $decoded['status']
                    ?? null;
                $epdsScore = $decoded['epds_total_score'] ?? $decoded['epds_score'] ?? $decoded['score'] ?? null;
                $conf = $decoded['confidence_score'] ?? $decoded['confidence'] ?? null;
                $isDepressed = $decoded['prediksi_depresi']
                    ?? (is_string($level) && preg_match('/tinggi|berat|sedang|depresi|high|moderate/i', $level) ? 1 : 0);

                $item->depression_level = $item->depression_level ?? ($level ?: ($isDepressed ? 'Berisiko Depresi' : 'Normal / Rendah'));
                $item->prediksi_depresi = $item->prediksi_depresi ?? $isDepressed;
                $item->epds_total_score = $item->epds_total_score ?? $epdsScore;
                $item->confidence_score = $item->confidence_score ?? $conf;
            }
        }
        return $item;
    }

    public function bidanIbuHamil(Request $request): JsonResponse
    {
        $this->assertAnyRole($request, ['bidan', 'dinkes', 'admin']);

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
                'user_profile.kota',
                'user_profile.kecamatan',
                'user_profile.provinsi',
                'user_profile.no_telepon',
                'user_profile.pendidikan_terakhir',
                'user_profile.pekerjaan',
                'user_profile.golongan_darah',
                'user_profile.photo',
                DB::raw('(SELECT activity_label FROM activity_logs WHERE activity_logs.user_id = users.user_id ORDER BY created_at DESC LIMIT 1) as last_activity_label'),
                DB::raw('(SELECT activity_label FROM activity_logs WHERE activity_logs.user_id = users.user_id ORDER BY created_at DESC LIMIT 1) as last_activity'),
                DB::raw('(SELECT activity_type FROM activity_logs WHERE activity_logs.user_id = users.user_id ORDER BY created_at DESC LIMIT 1) as last_activity_type')
            ])
            ->where('users.role', 'ibu_hamil')
            ->orderBy('users.created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                  ->orWhere('users.email', 'like', "%{$search}%")
                  ->orWhere('user_profile.kota', 'like', "%{$search}%")
                  ->orWhere('user_profile.alamat', 'like', "%{$search}%");
            });
        }

        $userIds = $query->pluck('users.user_id')->toArray();

        $anemiaHistory = collect();
        if (Schema::hasTable('health_scan_histories')) {
            $anemiaHistory = DB::table('health_scan_histories')
                ->where('type', 'anemia')
                ->whereIn('user_id', $userIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn ($item) => $this->normalizeAnemiaScanRow($item))
                ->groupBy('user_id');
        } elseif (Schema::hasTable('deteksipenyakit')) {
            $anemiaHistory = DB::table('deteksipenyakit')
                ->whereIn('user_id', $userIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('user_id');
        }

        $depressionHistory = collect();
        if (Schema::hasTable('health_scan_histories')) {
            $depressionHistory = DB::table('health_scan_histories')
                ->where('type', 'depression')
                ->whereIn('user_id', $userIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn ($item) => $this->normalizeDepressionScanRow($item))
                ->groupBy('user_id');
        } elseif (Schema::hasTable('prediksi_depresi')) {
            $depressionHistory = DB::table('prediksi_depresi')
                ->whereIn('user_id', $userIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('user_id');
        }

        $stuntingHistory = collect();
        if (Schema::hasTable('stunting_predictions')) {
            $stuntingHistory = DB::table('stunting_predictions')
                ->whereIn('user_id', $userIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('user_id');
        }

        $pregnancies = collect();
        if (Schema::hasTable('pregnancies')) {
            $pregnancies = DB::table('pregnancies')
                ->whereIn('user_id', $userIds)
                ->where('status', 'ongoing')
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('user_id');
        }

        $ibuHamil = $query->get()->map(function ($row) use ($anemiaHistory, $depressionHistory, $stuntingHistory, $pregnancies) {
            $row->photo_url = \App\Helpers\PhotoHelper::transformPhotoUrl($row->photo, 'public');

            $row->gestational_age_weeks = isset($pregnancies[$row->user_id])
                ? $pregnancies[$row->user_id]->first()->gestational_age_weeks
                : null;

            $row->anemia_history = isset($anemiaHistory[$row->user_id])
                ? $anemiaHistory[$row->user_id]->take(5)->values()
                : [];

            $row->depression_history = isset($depressionHistory[$row->user_id])
                ? $depressionHistory[$row->user_id]->take(5)->values()
                : [];

            $row->stunting_history = isset($stuntingHistory[$row->user_id])
                ? $stuntingHistory[$row->user_id]->take(5)->values()
                : [];

            return $row;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar ibu hamil beserta riwayat medis berhasil diambil.',
            'data'    => $ibuHamil,
        ]);
    }

    public function dinkesSurveillance(Request $request): JsonResponse
    {
        $this->assertAnyRole($request, ['dinkes', 'admin']);

        $ibuHamilResponse = $this->bidanIbuHamil($request)->getData(true);
        $patients = collect($ibuHamilResponse['data'] ?? []);

        $totalIbuHamil = $patients->count();
        $totalBidanAktif = DB::table('users')
            ->where('role', 'bidan')
            ->where('is_active', true)
            ->count();

        $totalLokasiPraktik = Schema::hasTable('bidan_locations')
            ? DB::table('bidan_locations')->where('is_active', true)->count()
            : 0;

        $totalKunjunganAnc = Schema::hasTable('catatan_ibu')
            ? DB::table('catatan_ibu')->count()
            : (Schema::hasTable('catatan') ? DB::table('catatan')->count() : 0);

        $anemiaRiskCount = 0;
        $stuntingRiskCount = 0;
        $depressionRiskCount = 0;
        $highRiskCount = 0;
        $mediumRiskCount = 0;
        $lowRiskCount = 0;
        $priorityPatients = [];
        $cityMap = [];

        foreach ($patients as $p) {
            $anemiaHist = $p['anemia_history'] ?? [];
            $stuntingHist = $p['stunting_history'] ?? [];
            $depressionHist = $p['depression_history'] ?? [];

            $hasAnemia = false;
            if (!empty($anemiaHist)) {
                $latest = $anemiaHist[0];
                $text = strtolower(($latest['hasil_mata_kiri'] ?? '') . ' ' . ($latest['hasil_mata_kanan'] ?? '') . ' ' . ($latest['prediction_label'] ?? '') . ' ' . ($latest['risk_level'] ?? ''));
                if ((str_contains($text, 'anemia') && !str_contains($text, 'tidak') && !str_contains($text, 'normal') && !str_contains($text, 'non')) || str_contains($text, 'high') || str_contains($text, 'tinggi') || str_contains($text, 'moderate') || str_contains($text, 'sedang')) {
                    $hasAnemia = true;
                }
            }

            $hasStunting = false;
            if (!empty($stuntingHist)) {
                $latest = $stuntingHist[0];
                $lvl = strtolower((string) ($latest['risk_level'] ?? ''));
                if (str_contains($lvl, 'tinggi') || str_contains($lvl, 'high') || str_contains($lvl, 'sedang') || str_contains($lvl, 'medium') || str_contains($lvl, 'moderate') || str_contains($lvl, 'berisiko')) {
                    $hasStunting = true;
                }
            }

            $hasDepression = false;
            if (!empty($depressionHist)) {
                $latest = $depressionHist[0];
                $lvl = strtolower((string) ($latest['depression_level'] ?? ''));
                $pred = (int) ($latest['prediksi_depresi'] ?? 0);
                if ($pred === 1 || str_contains($lvl, 'tinggi') || str_contains($lvl, 'berat') || str_contains($lvl, 'sedang') || str_contains($lvl, 'high') || str_contains($lvl, 'moderate') || str_contains($lvl, 'berisiko')) {
                    $hasDepression = true;
                }
            }

            if ($hasAnemia) {
                $anemiaRiskCount++;
            }
            if ($hasStunting) {
                $stuntingRiskCount++;
            }
            if ($hasDepression) {
                $depressionRiskCount++;
            }

            $riskFlags = [];
            if ($hasAnemia) {
                $riskFlags[] = 'Indikasi Anemia';
            }
            if ($hasStunting) {
                $riskFlags[] = 'Risiko Stunting/BBLR';
            }
            if ($hasDepression) {
                $riskFlags[] = 'Risiko Depresi Perinatal';
            }

            $flagCount = count($riskFlags);
            if ($flagCount >= 2 || ($hasAnemia && $hasStunting)) {
                $highRiskCount++;
                $triageTier = 'tinggi';
            } elseif ($flagCount === 1) {
                $mediumRiskCount++;
                $triageTier = 'sedang';
            } else {
                $lowRiskCount++;
                $triageTier = 'rendah';
            }

            $kota = trim((string) ($p['kota'] ?? '')) ?: 'Wilayah Belum Diisi';
            if (!isset($cityMap[$kota])) {
                $cityMap[$kota] = [
                    'kota' => $kota,
                    'total_ibu_hamil' => 0,
                    'kasus_berisiko' => 0,
                ];
            }
            $cityMap[$kota]['total_ibu_hamil']++;
            if ($flagCount > 0) {
                $cityMap[$kota]['kasus_berisiko']++;
            }

            if ($flagCount > 0) {
                $priorityPatients[] = [
                    'user_id' => $p['user_id'],
                    'name' => $p['name'],
                    'email' => $p['email'],
                    'usia' => $p['usia'] ?? null,
                    'gestational_age_weeks' => $p['gestational_age_weeks'] ?? null,
                    'kota' => $kota,
                    'alamat' => $p['alamat'] ?? '-',
                    'no_telepon' => $p['no_telepon'] ?? '-',
                    'triage_tier' => $triageTier,
                    'risk_flags' => $riskFlags,
                    'updated_at' => $p['updated_at'] ?? $p['created_at'] ?? null,
                ];
            }
        }

        usort($priorityPatients, function ($a, $b) {
            $weight = ['tinggi' => 3, 'sedang' => 2, 'rendah' => 1];
            return ($weight[$b['triage_tier']] ?? 0) <=> ($weight[$a['triage_tier']] ?? 0);
        });

        $wilayahDistribution = array_values($cityMap);
        usort($wilayahDistribution, fn ($a, $b) => $b['total_ibu_hamil'] <=> $a['total_ibu_hamil']);

        return response()->json([
            'status' => 'success',
            'message' => 'Data surveilans kesehatan ibu dan anak berhasil diambil.',
            'data' => [
                'summary' => [
                    'total_ibu_hamil' => $totalIbuHamil,
                    'total_bidan_aktif' => $totalBidanAktif,
                    'total_lokasi_praktik' => $totalLokasiPraktik,
                    'total_kunjungan_anc' => $totalKunjunganAnc,
                ],
                'triage_distribution' => [
                    'tinggi' => $highRiskCount,
                    'sedang' => $mediumRiskCount,
                    'rendah' => $lowRiskCount,
                ],
                'prevalence' => [
                    'anemia_count' => $anemiaRiskCount,
                    'anemia_pct' => $totalIbuHamil > 0 ? round(($anemiaRiskCount / $totalIbuHamil) * 100, 1) : 0,
                    'stunting_count' => $stuntingRiskCount,
                    'stunting_pct' => $totalIbuHamil > 0 ? round(($stuntingRiskCount / $totalIbuHamil) * 100, 1) : 0,
                    'depression_count' => $depressionRiskCount,
                    'depression_pct' => $totalIbuHamil > 0 ? round(($depressionRiskCount / $totalIbuHamil) * 100, 1) : 0,
                ],
                'wilayah_distribution' => array_slice($wilayahDistribution, 0, 8),
                'priority_patients' => array_slice($priorityPatients, 0, 15),
            ],
        ]);
    }
}
