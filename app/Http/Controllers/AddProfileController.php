<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Support\AuthToken;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class AddProfileController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        [$uid, $role] = \App\Support\AuthToken::ensureActiveAndFreshOrFail($request);
        if (!in_array($role, ['bidan','dinkes','ibu_hamil'], true)) {
            return response()->json(['status'=>'error','message'=>'Access denied: role not permitted.'], 403);
        }

        return match ($role) {
            'bidan'     => $this->createBidan($request, $uid),
            'dinkes'    => $this->createDinkes($request, $uid),
            'ibu_hamil' => $this->createIbuHamil($request, $uid),
        };
    }

    public function update(Request $request): JsonResponse
    {
        [$uid, $role] = \App\Support\AuthToken::ensureActiveAndFreshOrFail($request);
        if (!in_array($role, ['bidan','dinkes','ibu_hamil'], true)) {
            return response()->json(['status'=>'error','message'=>'Access denied: role not permitted.'], 403);
        }
        return match ($role) {
            'bidan'     => $this->updateBidan($request, $uid),
            'dinkes'    => $this->updateDinkes($request, $uid),
            'ibu_hamil' => $this->updateIbuHamil($request, $uid),
        };
    }

    protected function claimsOrFail(Request $request, array $allowedRoles): array
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
            $uid  = (int) $payload->get('uid');

            if (!$role || !$uid) {
                abort(response()->json([
                    'status'  => 'error',
                    'message' => 'Token missing required claims (role/uid).',
                ], 401));
            }

            $allowedRoles = array_map(fn($r) => strtolower(trim($r)), $allowedRoles);
            if (!in_array($role, $allowedRoles, true)) {
                abort(response()->json([
                    'status'  => 'error',
                    'message' => 'Access denied: role not permitted.',
                ], 403));
            }

            return [$uid, $role];

        } catch (TokenExpiredException $e) {
            abort(response()->json(['status'=>'error','message'=>'Token has expired.'], 401));
        } catch (TokenInvalidException $e) {
            abort(response()->json(['status'=>'error','message'=>'Token is invalid (signature/claims).'], 401));
        } catch (JWTException $e) {
            abort(response()->json(['status'=>'error','message'=>'Unable to parse token.'], 401));
        } catch (\Throwable $e) {
            abort(response()->json(['status'=>'error','message'=>'Invalid or missing token.'], 401));
        }
    }

    protected function profileExists(string $table, int $uid): bool
    {
        return DB::table($table)->where('user_id', $uid)->exists();
    }

    protected function createBidan(Request $req, int $uid): JsonResponse
    {
        $v = Validator::make($req->all(), [
            'tempat_praktik'           => ['required','string','max:255'],
            'alamat_praktik'           => ['required','string','max:500'],
            'kota_tempat_praktik'      => ['required','string','max:255'],
            'kecamatan_tempat_praktik' => ['required','string','max:255'],
            'telepon_tempat_praktik'   => ['nullable','string','max:32'],
            'spesialisasi'             => ['nullable','string','max:255'],
            'photo'                    => ['sometimes','file','image','mimes:jpg,jpeg,png,webp','max:500'],
        ], [
            'photo.image' => 'File harus berupa foto',
            'photo.mimes' => 'File harus berupa foto',
            'photo.max'   => 'Ukuran file melebihi batas upload, pastikan file dibawah 500KB',
        ]);
        if ($v->fails()) return $this->validationFail($v->errors());

        if ($this->profileExists('bidan_profile', $uid)) {
            return response()->json(['status'=>'error','message'=>'Profile already exists.'], 409);
        }

        $path = null;
        if ($req->hasFile('photo')) {
            $path = $req->file('photo')->store('profiles/bidan', 'public');
        }

        DB::table('bidan_profile')->insert([
            'user_id'                   => $uid,
            'tempat_praktik'            => $req->tempat_praktik,
            'alamat_praktik'            => $req->alamat_praktik,
            'kota_tempat_praktik'       => $req->kota_tempat_praktik,
            'kecamatan_tempat_praktik'  => $req->kecamatan_tempat_praktik,
            'telepon_tempat_praktik'    => $req->telepon_tempat_praktik,
            'spesialisasi'              => $req->spesialisasi,
            'photo'                     => $path,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        $photoUrl = $path ? asset('storage/' . $path) : null;

        return response()->json([
            'status'  => 'success',
            'message' => 'Profile kamu berhasil ditambahkan',
            'data'    => [
            'tempat_praktik'            => $req->tempat_praktik,
            'alamat_praktik'            => $req->alamat_praktik,
            'kota_tempat_praktik'       => $req->kota_tempat_praktik,
            'kecamatan_tempat_praktik'  => $req->kecamatan_tempat_praktik,
            'telepon_tempat_praktik'    => $req->telepon_tempat_praktik,
            'spesialisasi'              => $req->spesialisasi,
            'photo'                     => $photoUrl,
            ],
        ], 201);
    }


    protected function createDinkes(Request $req, int $uid): JsonResponse
    {
        $v = Validator::make($req->all(), [
            'jabatan' => ['required','string','max:255'],
            'nip'     => ['required','string','max:64'],
            'photo'   => ['sometimes','file','image','mimes:jpg,jpeg,png,webp','max:500'],
        ], [
            'photo.image' => 'File harus berupa foto',
            'photo.mimes' => 'File harus berupa foto',
            'photo.max'   => 'Ukuran file melebihi batas upload, pastikan file dibawah 500KB',
        ]);
        if ($v->fails()) return $this->validationFail($v->errors());

        if ($this->profileExists('user_dinkes', $uid)) {
            return response()->json(['status'=>'error','message'=>'Profile already exists.'], 409);
        }

        $path = null;
        if ($req->hasFile('photo')) {
            $path = $req->file('photo')->store('profiles/dinkes', 'public');
        }

        DB::table('user_dinkes')->insert([
            'user_id'    => $uid,
            'jabatan'    => $req->jabatan,
            'nip'        => $req->nip,
            'photo'      => $path,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $photoUrl = $path ? asset('storage/' . $path) : null;

        return response()->json([
            'status'  => 'success',
            'message' => 'Profile kamu berhasil ditambahkan',
            'data'    => [
                'jabatan'    => $req->jabatan,
                'nip'        => $req->nip,
                'photo'      => $photoUrl,
            ],
        ], 201);

    }


    protected function createIbuHamil(Request $req, int $uid): JsonResponse
    {
        $v = Validator::make($req->all(), [
            'tanggal_lahir'       => ['required','date'],
            'usia'                => ['nullable','integer'],
            'alamat'              => ['nullable','string','max:500'],
            'no_telepon'          => ['nullable','string','max:32'],
            'pendidikan_terakhir' => ['nullable','string','max:255'],
            'pekerjaan'           => ['nullable','string','max:255'],
            'golongan_darah'      => ['nullable','string','max:3'],
            'photo'               => ['sometimes','file','image','mimes:jpg,jpeg,png,webp','max:500'],
        ], [
            'photo.image' => 'File harus berupa foto',
            'photo.mimes' => 'File harus berupa foto',
            'photo.max'   => 'Ukuran file melebihi batas upload, pastikan file dibawah 500KB',
        ]);
        if ($v->fails()) return $this->validationFail($v->errors());

        if ($this->profileExists('user_profile', $uid)) {
            return response()->json(['status'=>'error','message'=>'Profile already exists.'], 409);
        }

        $path = null;
        if ($req->hasFile('photo')) {
            $path = $req->file('photo')->store('profiles/ibu', 'public');
        }

        DB::table('user_profile')->insert([
            'user_id'             => $uid,
            'tanggal_lahir'       => $req->tanggal_lahir,
            'usia'                => $req->usia,
            'alamat'              => $req->alamat,
            'no_telepon'          => $req->no_telepon,
            'pendidikan_terakhir' => $req->pendidikan_terakhir,
            'pekerjaan'           => $req->pekerjaan,
            'golongan_darah'      => $req->golongan_darah,
            'photo'               => $path,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        
        $photoUrl = $path ? asset('storage/' . $path) : null;

        return response()->json([
            'status'  => 'success',
            'message' => 'Profile kamu berhasil ditambahkan',
            'data'    => [
                'tanggal_lahir'       => $req->tanggal_lahir,
                'usia'                => $req->usia,
                'alamat'              => $req->alamat,
                'no_telepon'          => $req->no_telepon,
                'pendidikan_terakhir' => $req->pendidikan_terakhir,
                'pekerjaan'           => $req->pekerjaan,
                'golongan_darah'      => $req->golongan_darah,
                'photo'               => $photoUrl,
            ],
        ], 201);

    }


    protected function updateBidan(Request $req, int $uid): JsonResponse
    {
        $v = Validator::make($req->all(), [
            'tempat_praktik'           => ['sometimes','required','string','max:255'],
            'alamat_praktik'           => ['sometimes','required','string','max:500'],
            'kota_tempat_praktik'      => ['sometimes','required','string','max:255'],
            'kecamatan_tempat_praktik' => ['sometimes','required','string','max:255'],
            'telepon_tempat_praktik'   => ['nullable','string','max:32'],
            'spesialisasi'             => ['nullable','string','max:255'],
            'photo'                    => ['sometimes','file','image','mimes:jpg,jpeg,png,webp','max:500'],
        ], [
            'photo.image' => 'File harus berupa foto',
            'photo.mimes' => 'File harus berupa foto',
            'photo.max'   => 'Ukuran file melebihi batas upload, pastikan file dibawah 500KB',
        ]);
        if ($v->fails()) return $this->validationFail($v->errors());

        $existing = DB::table('bidan_profile')->where('user_id', $uid)->first();
        if (! $existing) return $this->notFound('Profile not found.');

        $data = $this->onlyNotNull($req->only([
            'tempat_praktik','alamat_praktik','kota_tempat_praktik',
            'kecamatan_tempat_praktik','telepon_tempat_praktik','spesialisasi'
        ]));

        if ($req->hasFile('photo')) {
            $newPath = $req->file('photo')->store('profiles/bidan', 'public');
            $data['photo'] = $newPath;

            if (!empty($existing->photo) && Storage::disk('public')->exists($existing->photo)) {
                Storage::disk('public')->delete($existing->photo);
            }
        }

        if (!$data) return $this->badRequest('No fields to update.');
        $data['updated_at'] = now();

        DB::table('bidan_profile')->where('user_id', $uid)->update($data);

        // Log profil update
        $user = User::select('user_id','name','email','role')->find($uid);
        if ($user) {
            ActivityLogService::logFromUser(
                ActivityLog::TYPE_UPDATE_PROFILE,
                $user,
                "Bidan {$user->name} berhasil mengupdate profil praktik.",
                request: $req
            );
        }

        return response()->json(['status'=>'success','message'=>'Profile kamu berhasil diupdate']);
    }


    protected function updateDinkes(Request $req, int $uid): JsonResponse
    {
        $v = Validator::make($req->all(), [
            'jabatan' => ['sometimes','required','string','max:255'],
            'nip'     => ['sometimes','required','string','max:64'],
            'photo'   => ['sometimes','file','image','mimes:jpg,jpeg,png,webp','max:500'],
        ], [
            'photo.image' => 'File harus berupa foto',
            'photo.mimes' => 'File harus berupa foto',
            'photo.max'   => 'Ukuran file melebihi batas upload, pastikan file dibawah 500KB',
        ]);
        if ($v->fails()) return $this->validationFail($v->errors());

        $existing = DB::table('user_dinkes')->where('user_id', $uid)->first();
        if (! $existing) return $this->notFound('Profile not found.');

        $data = $this->onlyNotNull($req->only(['jabatan','nip']));

        if ($req->hasFile('photo')) {
            $newPath = $req->file('photo')->store('profiles/dinkes', 'public');
            $data['photo'] = $newPath;

            if (!empty($existing->photo) && Storage::disk('public')->exists($existing->photo)) {
                Storage::disk('public')->delete($existing->photo);
            }
        }

        if (!$data) return $this->badRequest('No fields to update.');
        $data['updated_at'] = now();

        DB::table('user_dinkes')->where('user_id', $uid)->update($data);

        // Log profil update
        $user = User::select('user_id','name','email','role')->find($uid);
        if ($user) {
            ActivityLogService::logFromUser(
                ActivityLog::TYPE_UPDATE_PROFILE,
                $user,
                "Pegawai Dinkes {$user->name} berhasil mengupdate profil.",
                request: $req
            );
        }

        return response()->json(['status'=>'success','message'=>'Akun kamu berhasil diupdate']);
    }


    protected function updateIbuHamil(Request $req, int $uid): JsonResponse
    {
        $v = Validator::make($req->all(), [
            'tanggal_lahir'       => ['sometimes','required','date'],
            'usia'                => ['nullable','integer'],
            'alamat'              => ['nullable','string','max:500'],
            'no_telepon'          => ['nullable','string','max:32'],
            'pendidikan_terakhir' => ['nullable','string','max:255'],
            'pekerjaan'           => ['nullable','string','max:255'],
            'golongan_darah'      => ['nullable','string','max:3'],
            'photo'               => ['sometimes','file','image','mimes:jpg,jpeg,png,webp','max:500'],
        ], [
            'photo.image' => 'File harus berupa foto',
            'photo.mimes' => 'File harus berupa foto',
            'photo.max'   => 'Ukuran file melebihi batas upload, pastikan file dibawah 500KB',
        ]);
        if ($v->fails()) return $this->validationFail($v->errors());

        $existing = DB::table('user_profile')->where('user_id', $uid)->first();
        if (! $existing) return $this->notFound('Profile not found.');

        $data = $this->onlyNotNull($req->only([
            'tanggal_lahir','usia','alamat','no_telepon',
            'pendidikan_terakhir','pekerjaan','golongan_darah'
        ]));

        if ($req->hasFile('photo')) {
            $newPath = $req->file('photo')->store('profiles/ibu', 'public');
            $data['photo'] = $newPath;

            if (!empty($existing->photo) && Storage::disk('public')->exists($existing->photo)) {
                Storage::disk('public')->delete($existing->photo);
            }
        }

        if (!$data) return $this->badRequest('No fields to update.');
        $data['updated_at'] = now();

        DB::table('user_profile')->where('user_id', $uid)->update($data);

        // Log profil update
        $user = User::select('user_id','name','email','role')->find($uid);
        if ($user) {
            ActivityLogService::logFromUser(
                ActivityLog::TYPE_UPDATE_PROFILE,
                $user,
                "Ibu {$user->name} berhasil mengupdate profil biodata.",
                request: $req
            );
        }

        return response()->json(['status'=>'success','message'=>'Profile kamu berhasil diupdate']);
        
    }


    public function show(Request $request)
    {
        [$uid, $role] = AuthToken::assertRoleFresh($request, ['ibu_hamil','bidan','dinkes']);

        switch ($role) {
            case 'ibu_hamil':
                $profile = DB::table('user_profile')
                    ->where('user_id', $uid)
                    ->first();
                break;

            case 'bidan':
                $profile = DB::table('bidan_profile')
                    ->where('user_id', $uid)
                    ->first();
                break;

            case 'dinkes':
                $profile = DB::table('user_dinkes')
                    ->where('user_id', $uid)
                    ->first();
                break;
        }

        if (! $profile) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data profile tidak ditemukan, segera tambahkan data profile',
            ], 404);
        }

        
        if (!empty($profile->photo)) {
            $profile->photo = Storage::disk('public')->url($profile->photo);
        }

        return response()->json([
            'status'  => 'success',
            'profile' => $profile,
        ]);
    }

    protected function onlyNotNull(array $data): array
    {
        return array_filter($data, static fn($v) => !is_null($v));
    }

    protected function validationFail($errors): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => 'Validation failed.',
            'errors'  => $errors,
        ], 422);
    }

    protected function notFound(string $msg): JsonResponse
    {
        return response()->json(['status'=>'error','message'=>$msg], 404);
    }

    protected function conflict(string $msg): JsonResponse
    {
        return response()->json(['status'=>'error','message'=>$msg], 409);
    }

    protected function badRequest(string $msg): JsonResponse
    {
        return response()->json(['status'=>'error','message'=>$msg], 400);
    }
}