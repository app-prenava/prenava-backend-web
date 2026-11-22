<?php

namespace App\Http\Controllers;

use App\Models\BidanApplication;
use App\Models\BidanLocation;
use App\Models\BidanSubscription;
use App\Models\Appointment;
use App\Models\AppointmentConsent;
use App\Models\User;
use App\Support\AuthToken;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UserBidanController extends Controller
{
    /**
     * Scope query to only active, verified Prenava partner bidans
     * (approved application with STR & SIP + active non-expired subscription)
     */
    protected function applyVerifiedPartnerFilter($userQuery): void
    {
        $today = Carbon::today()->toDateString();

        $userQuery->where('is_active', true)
            ->where('role', 'bidan')
            ->whereHas('bidanSubscriptions', function ($sub) use ($today) {
                $sub->where('status', BidanSubscription::STATUS_ACTIVE)
                    ->whereDate('end_date', '>=', $today)
                    ->whereHas('application', function ($app) {
                        $app->where('status', BidanApplication::STATUS_APPROVED)
                            ->whereNotNull('str_number')
                            ->where('str_number', '!=', '')
                            ->whereNotNull('sip_number')
                            ->where('sip_number', '!=', '');
                    });
            });
    }

    /**
     * Get nearby verified partner bidan locations
     * GET /api/user/bidans/locations
     */
    public function getLocations(Request $request): JsonResponse
    {
        $query = BidanLocation::with([
            'bidan' => function ($q) {
                $q->select('user_id', 'name', 'email', 'is_active', 'role')
                  ->with([
                      'bidanProfile',
                      'bidanSubscription.application',
                  ]);
            },
        ])
            ->where('is_active', true)
            ->whereHas('bidan', function ($q) {
                $this->applyVerifiedPartnerFilter($q);
            });

        $hasCoords = $request->filled('lat') && $request->filled('lng');
        $lat = $hasCoords ? (float) $request->lat : null;
        $lng = $hasCoords ? (float) $request->lng : null;
        $radiusKm = $request->filled('radius_km') ? (float) $request->radius_km : null;

        // Apply bounding box pre-filter only when radius_km > 0
        if ($hasCoords && $radiusKm !== null && $radiusKm > 0) {
            $query->withinRadius($lat, $lng, $radiusKm);
        }

        // Optional text search across bidan name, practice name, address, regency, district
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('address_label', 'like', "%{$search}%")
                    ->orWhereHas('bidan', function ($bq) use ($search) {
                        $bq->where('name', 'like', "%{$search}%")
                            ->orWhereHas('bidanProfile', function ($pq) use ($search) {
                                $pq->where('tempat_praktik', 'like', "%{$search}%")
                                    ->orWhere('alamat_praktik', 'like', "%{$search}%")
                                    ->orWhere('kota_tempat_praktik', 'like', "%{$search}%")
                                    ->orWhere('kecamatan_tempat_praktik', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $locations = $query->get();

        // Format response with full clinic & verification metadata
        $result = $locations->map(function ($location) use ($hasCoords, $lat, $lng) {
            /** @var \App\Models\BidanLocation $location */
            $bidan = $location->bidan;
            $profile = $bidan?->bidanProfile;
            $application = $bidan?->bidanSubscription?->application;

            $photoUrl = null;
            if (!empty($profile?->photo)) {
                $photoUrl = str_starts_with($profile->photo, 'http')
                    ? $profile->photo
                    : url('storage/' . ltrim($profile->photo, '/'));
            }

            $data = [
                'id' => $location->bidan_id,
                'location_id' => $location->bidan_location_id,
                'bidan_id' => $location->bidan_id,
                'name' => $profile?->tempat_praktik ?: ($bidan?->name ?? 'Unknown'),
                'bidan_name' => $bidan?->name ?? 'Unknown',
                'tempat_praktik' => $profile?->tempat_praktik ?? $application?->bidan_name ?? $bidan?->name,
                'specialization' => $profile?->spesialisasi ?? $application?->spesialisasi,
                'spesialisasi' => $profile?->spesialisasi ?? $application?->spesialisasi,
                'photo_url' => $photoUrl,
                'lat' => (float) $location->lat,
                'lng' => (float) $location->lng,
                'latitude' => (float) $location->lat,
                'longitude' => (float) $location->lng,
                'address' => $location->address_label ?: ($profile?->alamat_praktik ?? ''),
                'address_label' => $location->address_label,
                'alamat_praktik' => $profile?->alamat_praktik ?? $location->address_label,
                'province_code' => $profile?->province_code ?? $application?->province_code,
                'province_name' => $profile?->provinsi_tempat_praktik ?? $application?->province,
                'regency_code' => $profile?->regency_code ?? $application?->regency_code,
                'regency_name' => $profile?->kota_tempat_praktik ?? $application?->city,
                'district_code' => $profile?->district_code ?? $application?->district_code,
                'district_name' => $profile?->kecamatan_tempat_praktik ?? $application?->district,
                'phone' => $location->effective_phone ?? $application?->phone ?? '',
                'notes' => $location->notes,
                'operating_hours' => $location->operating_hours ?? $application?->operating_hours,
                'str_number' => $profile?->str_number ?? $application?->str_number,
                'sip_number' => $profile?->sip_number ?? $application?->sip_number,
                'is_verified_partner' => true,
            ];

            if ($hasCoords) {
                $data['distance_km'] = $location->distanceFrom($lat, $lng);
            }

            return $data;
        });

        // Exact Haversine radius filter & sort by nearest distance
        if ($hasCoords) {
            if ($radiusKm !== null && $radiusKm > 0) {
                $result = $result->filter(fn ($item) => $item['distance_km'] <= $radiusKm);
            }
            $result = $result->sortBy('distance_km')->values();
        } else {
            $result = $result->values();
        }

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Get verified partner bidan detail
     * GET /api/user/bidans/{id}
     */
    public function getBidanDetail(int $bidanId): JsonResponse
    {
        $bidanQuery = User::where('user_id', $bidanId)
            ->with(['bidanProfile', 'bidanSubscription.application']);
        $this->applyVerifiedPartnerFilter($bidanQuery);
        $bidan = $bidanQuery->first();

        if (!$bidan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bidan mitra terverifikasi tidak ditemukan',
            ], 404);
        }

        // Get bidan's active locations
        $locations = BidanLocation::where('bidan_id', $bidanId)
            ->where('is_active', true)
            ->get();

        $profile = $bidan->bidanProfile;
        $application = $bidan->bidanSubscription?->application;

        $photoUrl = null;
        if (!empty($profile?->photo)) {
            $photoUrl = str_starts_with($profile->photo, 'http')
                ? $profile->photo
                : url('storage/' . ltrim($profile->photo, '/'));
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'bidan_id' => $bidan->user_id,
                'name' => $bidan->name,
                'is_verified_partner' => true,
                'str_number' => $profile?->str_number ?? $application?->str_number,
                'sip_number' => $profile?->sip_number ?? $application?->sip_number,
                'profile' => $profile ? [
                    'tempat_praktik' => $profile->tempat_praktik,
                    'alamat_praktik' => $profile->alamat_praktik,
                    'provinsi_tempat_praktik' => $profile->provinsi_tempat_praktik,
                    'kota_tempat_praktik' => $profile->kota_tempat_praktik,
                    'kecamatan_tempat_praktik' => $profile->kecamatan_tempat_praktik,
                    'telepon_tempat_praktik' => $profile->telepon_tempat_praktik,
                    'spesialisasi' => $profile->spesialisasi,
                    'photo' => $photoUrl,
                ] : null,
                'locations' => $locations->map(fn ($loc) => [
                    'location_id' => $loc->bidan_location_id,
                    'lat' => (float) $loc->lat,
                    'lng' => (float) $loc->lng,
                    'address_label' => $loc->address_label,
                    'phone' => $loc->effective_phone,
                    'operating_hours' => $loc->operating_hours,
                    'is_primary' => $loc->is_primary,
                ]),
            ],
        ]);
    }

    /**
     * Get consent text and fields
     * GET /api/user/consent-info
     */
    public function getConsentInfo(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'consent_version' => AppointmentConsent::CURRENT_VERSION,
                'consent_text' => AppointmentConsent::DEFAULT_CONSENT_TEXT,
                'available_fields' => [
                    'name' => 'Nama lengkap',
                    'email' => 'Email',
                    'phone' => 'Nomor telepon',
                    'address' => 'Alamat',
                    'age' => 'Usia',
                    'pregnancy_week' => 'Usia kehamilan',
                ],
            ],
        ]);
    }

    /**
     * Create appointment with consent
     * POST /api/user/appointments
     */
    public function createAppointment(Request $request): JsonResponse
    {
        [$userId, $role] = AuthToken::uidRoleOrFail($request);

        // Only ibu_hamil can create appointments
        if ($role !== 'ibu_hamil') {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya ibu hamil yang dapat membuat appointment',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'bidan_id' => 'required|exists:users,user_id',
            'location_id' => 'nullable|exists:bidan_locations,bidan_location_id',
            'preferred_date' => 'nullable|date|after_or_equal:today',
            'preferred_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:500',
            'consultation_type' => 'nullable|in:visit,consultation,checkup',
            'consent_accepted' => 'required|boolean|accepted',
            'consent_version' => 'required|string',
            'shared_fields' => 'required|array',
            'shared_fields.name' => 'boolean',
            'shared_fields.email' => 'boolean',
            'shared_fields.phone' => 'boolean',
            'shared_fields.address' => 'boolean',
            'shared_fields.age' => 'boolean',
            'shared_fields.pregnancy_week' => 'boolean',
        ], [
            'bidan_id.required' => 'Pilih bidan terlebih dahulu',
            'bidan_id.exists' => 'Bidan tidak ditemukan',
            'consent_accepted.required' => 'Persetujuan wajib diberikan',
            'consent_accepted.accepted' => 'Anda harus menyetujui syarat dan ketentuan',
            'shared_fields.required' => 'Pilih data yang akan dibagikan',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify bidan exists and is active
        $bidan = User::where('user_id', $request->bidan_id)
            ->where('role', 'bidan')
            ->where('is_active', true)
            ->first();

        if (!$bidan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bidan tidak aktif atau tidak ditemukan',
            ], 404);
        }

        // Verify location if provided
        if ($request->location_id) {
            $location = BidanLocation::where('bidan_location_id', $request->location_id)
                ->where('bidan_id', $request->bidan_id)
                ->where('is_active', true)
                ->first();

            if (!$location) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lokasi tidak valid',
                ], 400);
            }
        }

        // Check for existing pending appointment with same bidan
        $existingAppointment = Appointment::where('user_id', $userId)
            ->where('bidan_id', $request->bidan_id)
            ->whereIn('status', ['requested', 'accepted'])
            ->first();

        if ($existingAppointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda sudah memiliki appointment aktif dengan bidan ini',
            ], 409);
        }

        try {
            DB::beginTransaction();

            // Create appointment
            $appointment = Appointment::create([
                'user_id' => $userId,
                'bidan_id' => $request->bidan_id,
                'bidan_location_id' => $request->location_id,
                'status' => 'requested',
                'preferred_date' => $request->preferred_date,
                'preferred_time' => $request->preferred_time,
                'notes' => $request->notes,
                'consultation_type' => $request->consultation_type ?? 'consultation',
            ]);

            // Create consent record
            $consent = AppointmentConsent::createForAppointment(
                $userId,
                $appointment->appointment_id,
                $request->shared_fields,
                $request->ip(),
                $request->userAgent()
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Appointment berhasil dibuat. Menunggu konfirmasi dari bidan.',
                'data' => [
                    'appointment_id' => $appointment->appointment_id,
                    'bidan_id' => $appointment->bidan_id,
                    'bidan_location_id' => $appointment->bidan_location_id,
                    'status' => $appointment->status,
                    'bidan_name' => $bidan->name,
                    'preferred_date' => $appointment->preferred_date instanceof \Carbon\Carbon
                        ? $appointment->preferred_date->toDateString()
                        : $appointment->preferred_date,
                    'preferred_time' => $appointment->preferred_time ?? '',
                    'consultation_type' => $appointment->consultation_type ?? 'consultation',
                    'notes' => $appointment->notes,
                    'consent_id' => $consent->consent_id,
                    'consent_accepted' => true,
                    'consent_version' => $consent->consent_version ?? AppointmentConsent::CURRENT_VERSION,
                    'shared_fields' => $consent->shared_fields ?? $request->shared_fields,
                    'created_at' => $appointment->created_at?->toIso8601String(),
                    'updated_at' => $appointment->updated_at?->toIso8601String(),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membuat appointment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's appointments
     * GET /api/user/appointments
     */
    public function getAppointments(Request $request): JsonResponse
    {
        [$userId] = AuthToken::uidRoleOrFail($request);

        $query = Appointment::with(['bidan:user_id,name,email', 'location', 'consent'])
            ->where('user_id', $userId);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $appointments = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $appointments,
        ]);
    }

    /**
     * Get single appointment detail
     * GET /api/user/appointments/{id}
     */
    public function getAppointmentDetail(Request $request, int $id): JsonResponse
    {
        [$userId] = AuthToken::uidRoleOrFail($request);

        $appointment = Appointment::with(['bidan:user_id,name,email', 'location', 'consent'])
            ->where('user_id', $userId)
            ->where('appointment_id', $id)
            ->first();

        if (!$appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $appointment,
        ]);
    }

    /**
     * Cancel appointment
     * PATCH /api/user/appointments/{id}/cancel
     */
    public function cancelAppointment(Request $request, int $id): JsonResponse
    {
        [$userId] = AuthToken::uidRoleOrFail($request);

        $appointment = Appointment::where('user_id', $userId)
            ->where('appointment_id', $id)
            ->first();

        if (!$appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment tidak ditemukan',
            ], 404);
        }

        if (!in_array($appointment->status, ['requested', 'accepted'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment tidak dapat dibatalkan',
            ], 400);
        }

        $appointment->cancel($request->reason);

        return response()->json([
            'status' => 'success',
            'message' => 'Appointment berhasil dibatalkan',
            'data' => $appointment->fresh(),
        ]);
    }

    /**
     * Reschedule appointment (user requests new date/time)
     * PATCH /api/user/appointments/{id}/reschedule
     */
    public function rescheduleAppointment(Request $request, int $id): JsonResponse
    {
        [$userId] = AuthToken::uidRoleOrFail($request);

        $appointment = Appointment::where('user_id', $userId)
            ->where('appointment_id', $id)
            ->first();

        if (!$appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment tidak ditemukan',
            ], 404);
        }

        if (!$appointment->canReschedule()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment tidak dapat dijadwalkan ulang',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'preferred_date' => 'required|date|after_or_equal:today',
            'preferred_time' => 'required|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $appointment->reschedule(
            $request->preferred_date,
            $request->preferred_time,
            'user'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Appointment berhasil dijadwalkan ulang. Menunggu konfirmasi bidan.',
            'data' => $appointment->fresh(),
        ]);
    }

    /**
     * Get appointment statistics for user
     * GET /api/user/appointments/stats
     */
    public function getAppointmentStats(Request $request): JsonResponse
    {
        [$userId] = AuthToken::uidRoleOrFail($request);

        $stats = [
            'requested' => Appointment::where('user_id', $userId)->where('status', 'requested')->count(),
            'accepted' => Appointment::where('user_id', $userId)->where('status', 'accepted')->count(),
            'completed' => Appointment::where('user_id', $userId)->where('status', 'completed')->count(),
            'cancelled' => Appointment::where('user_id', $userId)->where('status', 'cancelled')->count(),
            'rejected' => Appointment::where('user_id', $userId)->where('status', 'rejected')->count(),
            'rescheduled' => Appointment::where('user_id', $userId)->where('status', 'rescheduled')->count(),
            'total' => Appointment::where('user_id', $userId)->count(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Get consultation types
     * GET /api/user/consultation-types
     */
    public function getConsultationTypes(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                ['key' => 'visit', 'label' => 'Kunjungan'],
                ['key' => 'consultation', 'label' => 'Konsultasi'],
                ['key' => 'checkup', 'label' => 'Pemeriksaan'],
            ],
        ]);
    }
}

