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

        $gestationalAgeWeeks = round(
            Carbon::parse($preg->lmp_date)->diffInDays(now()) / 7,
            1
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

        $forward = [
            'age'                      => $age,
            'gestational_age_weeks'    => (int) $gestationalAgeWeeks,
            'bmi'                      => (float) $a->bmi,

            'blood_pressure_systolic'  => $a->hypertension ? 150 : 90,
            'blood_pressure_diastolic' => $a->hypertension ? 100 : 80,
            'blood_sugar'              => $a->is_diabetes ? 200 : 80,
            'body_temp'                => $a->is_fever ? 40.0 : 36.5,
            'heart_rate'               => $a->is_high_heart_rate ? 120 : 80,

            'previous_complications'   => (bool) $a->previous_complications,
            'preexisting_diabetes'     => (bool) $a->is_diabetes,
            'gestational_diabetes'     => (bool) $a->gestational_diabetes,
            'mental_health_issue'      => (bool) $a->mental_health_issue,
            'placenta_position_restriction' => (bool) ($a->placenta_position_restriction ?? false),

            'low_impact_pref' => (bool) $a->low_impact_pref,
            'water_access'    => (bool) $a->water_access,
            'back_pain'       => (bool) $a->back_pain,
        ];

        $mlUrl = rtrim(env('URL_ML_SPORTS', 'http://72.61.213.163:8080'), '/') . '/predict';

        $resp = Http::withOptions(['timeout' => 15])
            ->retry(2, 500)
            ->acceptJson()
            ->post($mlUrl, $forward);

        if (! $resp->ok()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Upstream prediction service error.',
                'upstream_status' => $resp->status(),
            ], 502);
        }

        $ml = $resp->json();

        $activities = collect($ml['recommendations'] ?? [])
            ->pluck('activity')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $meta = DB::table('data_ml_sport')
            ->whereIn('activity', $activities)
            ->get()
            ->keyBy('activity');

        $baseUrl = rtrim(env('APP_URL'), '/');

        $enrich = function ($item) use ($meta, $baseUrl) {
            $m = $meta[$item['activity']] ?? null;

            return array_merge($item, [
                'video_link' => $m?->video_link,
                'long_text'  => $m?->long_text,
                'picture_1'  => ($m && $m->picture_1) ? $baseUrl . $m->picture_1 : null,
                'picture_2'  => ($m && $m->picture_2) ? $baseUrl . $m->picture_2 : null,
                'picture_3'  => ($m && $m->picture_3) ? $baseUrl . $m->picture_3 : null,
            ]);
        };

        $ml['recommendations'] = array_map($enrich, $ml['recommendations'] ?? []);

        if (!empty($ml['all_ranked'])) {
            $ml['all_ranked'] = array_map($enrich, $ml['all_ranked']);
        }

        // Log rekomendasi gerakan
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
            'status'         => 'success',
            'need_update_data'  => $needUpdateData,
            'message'        => 'Sport recommendation fetched.',
            'forward_payload'=> $forward,
            'model_response' => $ml,
        ]);
    }
    public function createRecomendation(Request $request): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'ibu_hamil');

        $v = Validator::make($request->all(), [
            'bmi'                       => ['required','numeric','min:10','max:60'],
            'hypertension'              => ['required','boolean'],
            'is_diabetes'               => ['required','boolean'],
            'gestational_diabetes'      => ['required','boolean'],
            'is_fever'                  => ['required','boolean'],
            'is_high_heart_rate'        => ['required','boolean'],
            'previous_complications'    => ['required','boolean'],
            'mental_health_issue'       => ['required','boolean'],
            'low_impact_pref'           => ['required','boolean'],
            'water_access'              => ['required','boolean'],
            'back_pain'                 => ['required','boolean'],
            'placenta_position_restriction' => ['nullable','boolean'],
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

        $gestationalAgeWeeks = round(
            Carbon::parse($preg->lmp_date)->diffInDays(now()) / 7,
            1
        );

        $assessmentData = [
            'pregnancy_id'           => $preg->pregnancy_id,
            'bmi'                    => $d['bmi'],
            'hypertension'           => $d['hypertension'],
            'is_diabetes'            => $d['is_diabetes'],
            'gestational_diabetes'   => $d['gestational_diabetes'],
            'is_fever'               => $d['is_fever'],
            'is_high_heart_rate'     => $d['is_high_heart_rate'],
            'previous_complications' => $d['previous_complications'],
            'mental_health_issue'    => $d['mental_health_issue'],
            'back_pain'              => $d['back_pain'],
            'low_impact_pref'        => $d['low_impact_pref'],
            'water_access'           => $d['water_access'],
            'placenta_previa'        => (bool) ($d['placenta_position_restriction'] ?? false),
            'updated_at'             => now(),
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

        $forward = [
            'age'                        => $age,
            'gestational_age_weeks'      => (int) $gestationalAgeWeeks,
            'bmi'                        => (float) $d['bmi'],
            'blood_pressure_systolic'    => $d['hypertension'] ? 150 : 90,
            'blood_pressure_diastolic'   => $d['hypertension'] ? 100 : 80,
            'blood_sugar'                => $d['is_diabetes'] ? 200 : 80,
            'body_temp'                  => $d['is_fever'] ? 40.0 : 36.5,
            'heart_rate'                 => $d['is_high_heart_rate'] ? 120 : 80,
            'previous_complications'     => (bool) $d['previous_complications'],
            'preexisting_diabetes'       => (bool) $d['is_diabetes'],
            'gestational_diabetes'       => (bool) $d['gestational_diabetes'],
            'mental_health_issue'        => (bool) $d['mental_health_issue'],
            'placenta_position_restriction' => (bool) ($d['placenta_position_restriction'] ?? false),
            'low_impact_pref'            => (bool) $d['low_impact_pref'],
            'water_access'               => (bool) $d['water_access'],
            'back_pain'                  => (bool) $d['back_pain'],
        ];

        $resp = Http::timeout(15)
            ->retry(2, 500)
            ->acceptJson()
            ->post(rtrim(env('URL_ML_SPORTS', 'http://72.61.213.163:8080'), '/') . '/predict', $forward);

        if (! $resp->ok()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Upstream prediction service error.',
            ], 502);
        }

        $ml = $resp->json();

        /* =========================
        * Enrich metadata + APP_URL
        * ========================= */
        $activities = collect($ml['recommendations'] ?? [])
            ->pluck('activity')
            ->unique()
            ->values();

        $meta = DB::table('data_ml_sport')
            ->whereIn('activity', $activities)
            ->get()
            ->keyBy('activity');

        $appUrl = rtrim(config('app.url'), '/');
        $fallback = 'data not found';

        $map = function ($item) use ($meta, $fallback, $appUrl) {
            $m = $meta[$item['activity']] ?? null;

            foreach (['picture_1','picture_2','picture_3'] as $p) {
                if ($m && $m->$p) {
                    $m->$p = $appUrl . $m->$p;
                }
            }

            return array_merge($item, [
                'video_link' => $m?->video_link,
                'long_text'  => $m?->long_text,
                'picture_1'  => $m?->picture_1,
                'picture_2'  => $m?->picture_2,
                'picture_3'  => $m?->picture_3,
            ]);
        };

        $ml['recommendations'] = array_map($map, $ml['recommendations'] ?? []);

        if (! empty($ml['all_ranked'])) {
            $ml['all_ranked'] = array_map($map, $ml['all_ranked']);
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
            'status'         => 'success',
            'message'        => 'Sport recommendation success.',
            'model_response' => $ml,
        ], 200);
    }

    public function indexSportMeta(Request $request): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'admin');

        $q = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 200));

        $query = DB::table('data_ml_sport')
            ->select([
                'activity',
                'video_link',
                'long_text',
                'picture_1',
                'picture_2',
                'picture_3',
                'created_at',
                'updated_at',
            ])
            ->orderBy('activity');

        if ($q !== '') {
            $query->where('activity', 'like', '%' . $q . '%');
        }

        $items = $query->limit($perPage)->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'List sport metadata.',
            'data'    => $items,
        ], 200);
    }

    // Public endpoint to get all sports (for users)
    public function getAllSportsPublic(): JsonResponse
    {
        $baseUrl = rtrim(config('app.url'), '/');

        $items = DB::table('data_ml_sport')
            ->select([
                'activity',
                'video_link',
                'long_text',
                'picture_1',
                'picture_2',
                'picture_3',
            ])
            ->orderBy('activity')
            ->get();

        // Enrich picture URLs
        $items = $items->map(function ($item) use ($baseUrl) {
            foreach (['picture_1', 'picture_2', 'picture_3'] as $pic) {
                if ($item->$pic && !str_starts_with($item->$pic, 'http')) {
                    $item->$pic = $baseUrl . $item->$pic;
                }
            }
            // Add default score for display
            $item->score = 0.5; // Default neutral score
            return $item;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'List all sports.',
            'data'    => $items,
        ], 200);
    }

    public function showSportMeta(Request $request, string $activity): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'admin');

        $row = DB::table('data_ml_sport')
            ->select([
                'activity',
                'video_link',
                'long_text',
                'picture_1',
                'picture_2',
                'picture_3',
                'created_at',
                'updated_at',
            ])
            ->where('activity', $activity)
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
            'activity'   => ['required','string','max:100'],
            'video_link' => ['nullable','string','max:2048'],
            'long_text'  => ['nullable','string'],

            'picture_1'  => ['nullable','image','max:2048'],
            'picture_2'  => ['nullable','image','max:2048'],
            'picture_3'  => ['nullable','image','max:2048'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $v->errors(),
            ], 422);
        }

        $d = $v->validated();

        if (DB::table('data_ml_sport')->where('activity', $d['activity'])->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Activity already exists.',
            ], 409);
        }

        $folder = 'sports';
        $pictures = [
            'picture_1' => null,
            'picture_2' => null,
            'picture_3' => null,
        ];

        foreach ($pictures as $key => $_) {
            if ($request->hasFile($key)) {
                $path = $request->file($key)->store($folder, 'public');
                $pictures[$key] = '/storage/' . $path;
            }
        }

        $now = now();

        DB::table('data_ml_sport')->insert([
            'activity'   => $d['activity'],
            'video_link' => $d['video_link'] ?? null,
            'long_text'  => $d['long_text'] ?? null,
            'picture_1'  => $pictures['picture_1'],
            'picture_2'  => $pictures['picture_2'],
            'picture_3'  => $pictures['picture_3'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = DB::table('data_ml_sport')
            ->where('activity', $d['activity'])
            ->first();

        $appUrl = rtrim(config('app.url'), '/');

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


    public function updateSportMeta(Request $request, string $activity): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'admin');

        $v = Validator::make($request->all(), [
            'video_link' => ['nullable','string','max:2048'],
            'long_text'  => ['nullable','string'],

            'picture_1'  => ['nullable','file','image','max:5120'],
            'picture_2'  => ['nullable','file','image','max:5120'],
            'picture_3'  => ['nullable','file','image','max:5120'],

            'remove_picture_1' => ['nullable','boolean'],
            'remove_picture_2' => ['nullable','boolean'],
            'remove_picture_3' => ['nullable','boolean'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $v->errors(),
            ], 422);
        }

        $exists = DB::table('data_ml_sport')->where('activity', $activity)->exists();
        if (! $exists) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data not found.',
            ], 404);
        }

        $d = $v->validated();

        $folder = 'ml_sport';

        $old = DB::table('data_ml_sport')->where('activity', $activity)->first();

        $payload = ['updated_at' => now()];

        if ($request->has('video_link')) {
            $payload['video_link'] = $d['video_link'] ?? null;
        }
        if ($request->has('long_text')) {
            $payload['long_text'] = $d['long_text'] ?? null;
        }

        if ($request->hasFile('picture_1')) {
            $path = $request->file('picture_1')->store($folder, 'public');
            $payload['picture_1'] = '/storage/' . $path;
        } elseif (!empty($d['remove_picture_1'])) {
            $payload['picture_1'] = null;
        }

        if ($request->hasFile('picture_2')) {
            $path = $request->file('picture_2')->store($folder, 'public');
            $payload['picture_2'] = '/storage/' . $path;
        } elseif (!empty($d['remove_picture_2'])) {
            $payload['picture_2'] = null;
        }

        if ($request->hasFile('picture_3')) {
            $path = $request->file('picture_3')->store($folder, 'public');
            $payload['picture_3'] = '/storage/' . $path;
        } elseif (!empty($d['remove_picture_3'])) {
            $payload['picture_3'] = null;
        }

        DB::table('data_ml_sport')->where('activity', $activity)->update($payload);

        $row = DB::table('data_ml_sport')->where('activity', $activity)->first();

        return response()->json([
            'status'  => 'success',
            'message' => 'Sport metadata updated.',
            'data'    => $row,
        ], 200);
    }


    public function deleteSportMeta(Request $request, string $activity): JsonResponse
    {
        [$uid] = AuthToken::assertRoleFresh($request, 'admin');

        $deleted = DB::table('data_ml_sport')
            ->where('activity', $activity)
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
            'activity'=> $activity,
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
