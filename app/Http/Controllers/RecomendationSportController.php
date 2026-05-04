<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Support\AuthToken;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Models\User;
use Carbon\Carbon;

class RecomendationSportController extends Controller
{
    public function getSportRecommendation(): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh(request(), 'ibu_hamil');

        $profile = DB::table('user_profile')
            ->select('tanggal_lahir')
            ->where('user_id', $uid)
            ->first();

        if (! $profile || ! $profile->tanggal_lahir) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tanggal lahir tidak ditemukan.',
            ], 422);
        }

        $age = Carbon::parse($profile->tanggal_lahir)->age;

        $preg = DB::table('pregnancies')
            ->select('pregnancy_id', 'lmp_date')
            ->where('user_id', $uid)
            ->where('status', 'ongoing')
            ->orderByDesc('pregnancy_id')
            ->first();

        if (! $preg) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pregnancy ongoing dengan LMP tidak ditemukan.',
            ], 404);
        }

        $gestationalAgeWeeks = (int) round(
            Carbon::parse($preg->lmp_date)->diffInDays(now()) / 7
        );

        $a = DB::table('pregnancy_assessments')
            ->where('pregnancy_id', $preg->pregnancy_id)
            ->first();

        if (! $a) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Assessment belum tersedia.',
            ], 404);
        }

        $needUpdateData = Carbon::parse($a->updated_at)
            ->addDays(30)
            ->lessThanOrEqualTo(now());
        
        $totalSportData = DB::table('data_ml_sport')->count();

        if ($totalSportData === 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Sport activity data is not yet available.',
            ], 404);
        }

        // Payload baru sesuai struktur /predict
        $forward = [
            'model_input' => [
                'age'                    => $age,
                'systolic_bp'            => $a->hypertension ? 150 : 90,
                'diastolic_bp'           => $a->hypertension ? 100 : 80,
                'blood_sugar'            => $a->is_diabetes ? 200 : 80,
                'body_temp'              => $a->is_fever ? 40.0 : 36.5,
                'bmi'                    => (float) $a->bmi,
                'previous_complications' => (bool) $a->previous_complications,
                'preexisting_diabetes'   => (bool) $a->is_diabetes,
                'gestational_diabetes'   => (bool) $a->gestational_diabetes,
                'mental_health_issue'    => (bool) $a->mental_health_issue,
                'heart_rate'             => $a->is_high_heart_rate ? 120 : 80,
            ],
            'recommendation_context' => [
                'gestational_age_weeks'       => $gestationalAgeWeeks,
                'placenta_position_restriction' => (bool) ($a->placenta_position_restriction ?? false),
                'low_impact_preference'       => (bool) $a->low_impact_pref,
                'water_access'                => (bool) $a->water_access,
                'back_pain'                   => (bool) $a->back_pain,
            ],
        ];

        $mlUrl = rtrim(env('URL_ML_SPORTS', 'http://72.61.213.163:8080'), '/') . '/predict';

        $resp = Http::withOptions(['timeout' => 15])
            ->retry(2, 500)
            ->acceptJson()
            ->post($mlUrl, $forward);

        if (! $resp->ok()) {
            return response()->json([
                'status'          => 'error',
                'message'         => 'Upstream prediction service error.',
                'upstream_status' => $resp->status(),
            ], 502);
        }

        $ml = $resp->json();

        // Ambil semua code dari seluruh recommendation level
        $allCodes = collect($ml['recommendations'] ?? [])
            ->flatten(1)
            ->pluck('code')
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Ambil detail dari tabel data_ml_sport berdasarkan code
        $baseUrl = rtrim(env('APP_URL'), '/');

        $metaByCode = DB::table('data_ml_sport')
            ->whereIn('code', $allCodes)
            ->get()
            ->keyBy('code');

        // Enrich tiap item dengan detail dari DB
        $enrich = function (array $item) use ($metaByCode, $baseUrl): array {
            $code = $item['code'] ?? null;
            $m    = $code ? ($metaByCode[$code] ?? null) : null;

            return array_merge($item, [
                'video_link' => $m?->video_link,
                'long_text'  => $m?->long_text,
                'picture_1'  => ($m && $m->picture_1) ? $baseUrl . $m->picture_1 : null,
                'picture_2'  => ($m && $m->picture_2) ? $baseUrl . $m->picture_2 : null,
                'picture_3'  => ($m && $m->picture_3) ? $baseUrl . $m->picture_3 : null,
            ]);
        };

        // Enrich semua recommendation level
        $recommendations = $ml['recommendations'] ?? [];
        foreach ($recommendations as $level => $items) {
            $recommendations[$level] = array_map($enrich, $items);
        }

        // Enrich all jika ada
        $allRanked = isset($ml['recommendations']['all'])
            ? array_map($enrich, $ml['recommendations']['all'])
            : [];

        // Log aktivitas
        $user = User::find($uid);
        if ($user) {
            ActivityLogService::logFromUser(
                ActivityLog::TYPE_REKOMENDASI_GERAKAN,
                $user,
                "Ibu {$user->name} melihat rekomendasi gerakan/olahraga.",
                request: request()
            );
        }

        return response()->json([
            'status'          => 'success',
            'need_update_data'=> $needUpdateData,
            'message'         => 'Sport recommendation fetched.',
            'forward_payload' => $forward,
            'prediction'      => $ml['prediction'] ?? null,
            'recommendations' => $recommendations,
            'meta'            => $ml['meta'] ?? null,
        ]);
    }
    public function createRecomendation(Request $request): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'ibu_hamil');

        $v = Validator::make($request->all(), [
            'bmi'                           => ['required', 'numeric', 'min:10', 'max:60'],
            'hypertension'                  => ['required', 'boolean'],
            'is_diabetes'                   => ['required', 'boolean'],
            'gestational_diabetes'          => ['required', 'boolean'],
            'is_fever'                      => ['required', 'boolean'],
            'is_high_heart_rate'            => ['required', 'boolean'],
            'previous_complications'        => ['required', 'boolean'],
            'mental_health_issue'           => ['required', 'boolean'],
            'low_impact_pref'               => ['required', 'boolean'],
            'water_access'                  => ['required', 'boolean'],
            'back_pain'                     => ['required', 'boolean'],
            'placenta_position_restriction' => ['nullable', 'boolean'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $v->errors(),
            ], 422);
        }

        $d = $v->validated();

        $profile = DB::table('user_profile')
            ->select('tanggal_lahir')
            ->where('user_id', $uid)
            ->first();

        if (! $profile || ! $profile->tanggal_lahir) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tanggal lahir tidak ditemukan.',
            ], 422);
        }

        $age = Carbon::parse($profile->tanggal_lahir)->age;

        $preg = DB::table('pregnancies')
            ->select('pregnancy_id', 'lmp_date')
            ->where('user_id', $uid)
            ->where('status', 'ongoing')
            ->whereNotNull('lmp_date')
            ->orderByDesc('pregnancy_id')
            ->first();

        if (! $preg) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pregnancy ongoing dengan LMP tidak ditemukan.',
            ], 404);
        }

        $gestationalAgeWeeks = (int) round(
            Carbon::parse($preg->lmp_date)->diffInDays(now()) / 7
        );

        $assessmentData = [
            'pregnancy_id'                  => $preg->pregnancy_id,
            'bmi'                           => $d['bmi'],
            'hypertension'                  => $d['hypertension'],
            'is_diabetes'                   => $d['is_diabetes'],
            'gestational_diabetes'          => $d['gestational_diabetes'],
            'is_fever'                      => $d['is_fever'],
            'is_high_heart_rate'            => $d['is_high_heart_rate'],
            'previous_complications'        => $d['previous_complications'],
            'mental_health_issue'           => $d['mental_health_issue'],
            'back_pain'                     => $d['back_pain'],
            'low_impact_pref'               => $d['low_impact_pref'],
            'water_access'                  => $d['water_access'],
            'placenta_previa'               => (bool) ($d['placenta_position_restriction'] ?? false),
            'updated_at'                    => now(),
        ];

        $existing = DB::table('pregnancy_assessments')
            ->where('pregnancy_id', $preg->pregnancy_id)
            ->first();

        if ($existing) {
            DB::table('pregnancy_assessments')
                ->where('pregnancy_id', $preg->pregnancy_id)
                ->update($assessmentData);
        } else {
            DB::table('pregnancy_assessments')->insert(
                array_merge($assessmentData, ['created_at' => now()])
            );
        }

        // Payload baru sesuai struktur /predict
        $forward = [
            'model_input' => [
                'age'                    => $age,
                'systolic_bp'            => $d['hypertension'] ? 150 : 90,
                'diastolic_bp'           => $d['hypertension'] ? 100 : 80,
                'blood_sugar'            => $d['is_diabetes'] ? 200 : 80,
                'body_temp'              => $d['is_fever'] ? 40.0 : 36.5,
                'bmi'                    => (float) $d['bmi'],
                'previous_complications' => (bool) $d['previous_complications'],
                'preexisting_diabetes'   => (bool) $d['is_diabetes'],
                'gestational_diabetes'   => (bool) $d['gestational_diabetes'],
                'mental_health_issue'    => (bool) $d['mental_health_issue'],
                'heart_rate'             => $d['is_high_heart_rate'] ? 120 : 80,
            ],
            'recommendation_context' => [
                'gestational_age_weeks'         => $gestationalAgeWeeks,
                'placenta_position_restriction' => (bool) ($d['placenta_position_restriction'] ?? false),
                'low_impact_preference'         => (bool) $d['low_impact_pref'],
                'water_access'                  => (bool) $d['water_access'],
                'back_pain'                     => (bool) $d['back_pain'],
            ],
        ];

        $resp = Http::timeout(15)
            ->retry(2, 500)
            ->acceptJson()
            ->post(rtrim(env('URL_ML_SPORTS', 'http://72.61.213.163:8080'), '/') . '/predict', $forward);

        if (! $resp->ok()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Upstream prediction service error.',
            ], 502);
        }

        $ml = $resp->json();

        // Ambil semua code dari seluruh recommendation level
        $allCodes = collect($ml['recommendations'] ?? [])
            ->flatten(1)
            ->pluck('code')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $baseUrl = rtrim(env('APP_URL'), '/');

        $metaByCode = DB::table('data_ml_sport')
            ->whereIn('code', $allCodes)
            ->get()
            ->keyBy('code');

        if ($metaByCode->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Sport activity data is not yet available.',
            ], 404);
        }

        $enrich = function (array $item) use ($metaByCode, $baseUrl): array {
            $code = $item['code'] ?? null;
            $m    = $code ? ($metaByCode[$code] ?? null) : null;

            return array_merge($item, [
                'video_link' => $m?->video_link,
                'long_text'  => $m?->long_text,
                'picture_1'  => ($m && $m->picture_1) ? $baseUrl . $m->picture_1 : null,
                'picture_2'  => ($m && $m->picture_2) ? $baseUrl . $m->picture_2 : null,
                'picture_3'  => ($m && $m->picture_3) ? $baseUrl . $m->picture_3 : null,
            ]);
        };

        $recommendations = $ml['recommendations'] ?? [];
        foreach ($recommendations as $level => $items) {
            $recommendations[$level] = array_map($enrich, $items);
        }

        // Log pengisian assessment rekomendasi gerakan
        $user = User::find($uid);
        if ($user) {
            ActivityLogService::logFromUser(
                ActivityLog::TYPE_REKOMENDASI_GERAKAN,
                $user,
                "User {$user->name} memperbarui data assessment untuk rekomendasi gerakan/olahraga.",
                ['gestational_age_weeks' => $gestationalAgeWeeks, 'bmi' => $d['bmi']],
                $request
            );
        }

        return response()->json([
            'status'          => 'success',
            'message'         => 'Sport recommendation created successfully.',
            'forward_payload' => $forward,
            'prediction'      => $ml['prediction'] ?? null,
            'recommendations' => $recommendations,
            'meta'            => $ml['meta'] ?? null,
        ], 200);
    }

    public function indexSportMeta(Request $request): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'admin');

        $q       = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 200));

        $query = DB::table('data_ml_sport')
            ->select(['id', 'code', 'name', 'video_link', 'long_text', 'picture_1', 'picture_2', 'picture_3', 'created_at', 'updated_at'])
            ->orderBy('name');

        if ($q !== '') {
            $query->where(function ($q2) use ($q) {
                $q2->where('code', 'like', '%' . $q . '%')
                ->orWhere('name', 'like', '%' . $q . '%');
            });
        }

        $items = $query->limit($perPage)->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'List sport metadata.',
            'data'    => $items,
        ], 200);
    }

    public function getAllSportsPublic(): JsonResponse
    {
        $baseUrl = rtrim(config('app.url'), '/');

        $items = DB::table('data_ml_sport')
            ->select(['code', 'name', 'video_link', 'long_text', 'picture_1', 'picture_2', 'picture_3'])
            ->orderBy('name')
            ->get()
            ->map(function ($item) use ($baseUrl) {
                foreach (['picture_1', 'picture_2', 'picture_3'] as $pic) {
                    if ($item->$pic && ! str_starts_with($item->$pic, 'http')) {
                        $item->$pic = $baseUrl . $item->$pic;
                    }
                }
                return $item;
            });

        return response()->json([
            'status'  => 'success',
            'message' => 'List all sports.',
            'data'    => $items,
        ], 200);
    }

    public function showSportMeta(Request $request, string $code): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'admin');

        $row = DB::table('data_ml_sport')
            ->select(['id', 'code', 'name', 'video_link', 'long_text', 'picture_1', 'picture_2', 'picture_3', 'created_at', 'updated_at'])
            ->where('code', $code)
            ->first();

        if (! $row) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data not found.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Sport metadata detail.',
            'data'    => $row,
        ], 200);
    }

    public function storeSportMeta(Request $request): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'admin');

        $v = Validator::make($request->all(), [
            'code'       => ['required', 'string', 'max:100'],
            'name'       => ['required', 'string', 'max:150'],
            'video_link' => ['nullable', 'string', 'max:2048'],
            'long_text'  => ['nullable', 'string'],
            'picture_1'  => ['nullable', 'image', 'max:2048'],
            'picture_2'  => ['nullable', 'image', 'max:2048'],
            'picture_3'  => ['nullable', 'image', 'max:2048'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $v->errors(),
            ], 422);
        }

        $d = $v->validated();

        if (DB::table('data_ml_sport')->where('code', $d['code'])->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Sport activity with this code already exists.',
            ], 409);
        }

        $pictures = ['picture_1' => null, 'picture_2' => null, 'picture_3' => null];

        foreach ($pictures as $key => $_) {
            if ($request->hasFile($key)) {
                $path         = $request->file($key)->store('sports', 'public');
                $pictures[$key] = '/storage/' . $path;
            }
        }

        $now = now();

        DB::table('data_ml_sport')->insert([
            'code'       => $d['code'],
            'name'       => $d['name'],
            'video_link' => $d['video_link'] ?? null,
            'long_text'  => $d['long_text'] ?? null,
            'picture_1'  => $pictures['picture_1'],
            'picture_2'  => $pictures['picture_2'],
            'picture_3'  => $pictures['picture_3'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row     = DB::table('data_ml_sport')->where('code', $d['code'])->first();
        $appUrl  = rtrim(config('app.url'), '/');

        foreach (['picture_1', 'picture_2', 'picture_3'] as $pic) {
            if ($row->$pic) {
                $row->$pic = $appUrl . $row->$pic;
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Sport metadata created.',
            'data'    => $row,
        ], 201);
    }

    public function updateSportMeta(Request $request, string $code): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'admin');

        $v = Validator::make($request->all(), [
            'name'             => ['sometimes', 'string', 'max:150'],
            'video_link'       => ['nullable', 'string', 'max:2048'],
            'long_text'        => ['nullable', 'string'],
            'picture_1'        => ['nullable', 'file', 'image', 'max:5120'],
            'picture_2'        => ['nullable', 'file', 'image', 'max:5120'],
            'picture_3'        => ['nullable', 'file', 'image', 'max:5120'],
            'remove_picture_1' => ['nullable', 'boolean'],
            'remove_picture_2' => ['nullable', 'boolean'],
            'remove_picture_3' => ['nullable', 'boolean'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $v->errors(),
            ], 422);
        }

        $exists = DB::table('data_ml_sport')->where('code', $code)->exists();
        if (! $exists) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data not found.',
            ], 404);
        }

        $d       = $v->validated();
        $payload = ['updated_at' => now()];

        if ($request->has('name')) {
            $payload['name'] = $d['name'];
        }
        if ($request->has('video_link')) {
            $payload['video_link'] = $d['video_link'] ?? null;
        }
        if ($request->has('long_text')) {
            $payload['long_text'] = $d['long_text'] ?? null;
        }

        foreach (['picture_1', 'picture_2', 'picture_3'] as $key) {
            if ($request->hasFile($key)) {
                $path           = $request->file($key)->store('ml_sport', 'public');
                $payload[$key]  = '/storage/' . $path;
            } elseif (! empty($d['remove_' . $key])) {
                $payload[$key] = null;
            }
        }

        DB::table('data_ml_sport')->where('code', $code)->update($payload);

        $row = DB::table('data_ml_sport')->where('code', $code)->first();

        return response()->json([
            'status'  => 'success',
            'message' => 'Sport metadata updated.',
            'data'    => $row,
        ], 200);
    }

    public function deleteSportMeta(Request $request, string $code): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'admin');

        $deleted = DB::table('data_ml_sport')
            ->where('code', $code)
            ->delete();

        if ($deleted < 1) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data not found.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Sport metadata deleted.',
            'code'    => $code,
        ], 200);
    }

    public function getExistingAssessment(): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh(request(), 'ibu_hamil');

        $preg = DB::table('pregnancies')
            ->select('pregnancy_id')
            ->where('user_id', $uid)
            ->where('status', 'ongoing')
            ->orderByDesc('pregnancy_id')
            ->first();

        if (! $preg) {
            return response()->json([
                'status'         => 'success',
                'has_assessment' => false,
                'assessment'     => null,
            ]);
        }

        $a = DB::table('pregnancy_assessments')
            ->where('pregnancy_id', $preg->pregnancy_id)
            ->first();

        if (! $a) {
            return response()->json([
                'status'         => 'success',
                'has_assessment' => false,
                'assessment'     => null,
            ]);
        }

        return response()->json([
            'status'         => 'success',
            'has_assessment' => true,
            'assessment'     => [
                'bmi'                          => (float) $a->bmi,
                'hypertension'                 => (bool) $a->hypertension,
                'is_diabetes'                  => (bool) $a->is_diabetes,
                'gestational_diabetes'         => (bool) $a->gestational_diabetes,
                'is_fever'                     => (bool) $a->is_fever,
                'is_high_heart_rate'           => (bool) $a->is_high_heart_rate,
                'previous_complications'       => (bool) $a->previous_complications,
                'mental_health_issue'          => (bool) $a->mental_health_issue,
                'back_pain'                    => (bool) $a->back_pain,
                'low_impact_pref'              => (bool) $a->low_impact_pref,
                'water_access'                 => (bool) $a->water_access,
                'placenta_position_restriction'=> (bool) $a->placenta_previa,
                'updated_at'                   => $a->updated_at,
            ],
        ]);
    }
}
