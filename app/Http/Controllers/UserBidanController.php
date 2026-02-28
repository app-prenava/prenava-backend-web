<?php

namespace App\Http\Controllers;

use App\Models\BidanLocation;
use App\Models\Appointment;
use App\Models\AppointmentConsent;
use App\Models\User;
use App\Support\AuthToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UserBidanController extends Controller
{
    /**
     * Get nearby bidan locations (for map)
     * GET /api/user/bidans/locations
     */
    public function getLocations(Request $request): JsonResponse
    {
        $query = BidanLocation::with(['bidan' => function ($q) {
            $q->select('user_id', 'name', 'email')
              ->where('is_active', true)
              ->where('role', 'bidan');
        }])->where('is_active', true);

        // Filter by radius if coordinates provided
        if ($request->has('lat') && $request->has('lng')) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;
            $radiusKm = (float) ($request->radius_km ?? 10); // default 10km

            $query->withinRadius($lat, $lng, $radiusKm);
        }

        $locations = $query->get();

        // Calculate distance and format response
        $result = $locations->map(function ($location) use ($request) {
            /** @var \App\Models\BidanLocation $location */
            $data = [
                'location_id' => $location->bidan_location_id,
                'bidan_id' => $location->bidan_id,
                'bidan_name' => $location->bidan?->name ?? 'Unknown',
                'lat' => (float) $location->lat,
                'lng' => (float) $location->lng,
                'address_label' => $location->address_label,
                'phone' => $location->effective_phone,
                'notes' => $location->notes,
                'operating_hours' => $location->operating_hours,
            ];

            // Add distance if coordinates provided
            if ($request->has('lat') && $request->has('lng')) {
                $data['distance_km'] = $location->distanceFrom(
                    (float) $request->lat,
                    (float) $request->lng
                );
            }

            return $data;
        });

        // Sort by distance if applicable
        if ($request->has('lat') && $request->has('lng')) {
            $result = $result->sortBy('distance_km')->values();
        }

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Get bidan detail
     * GET /api/user/bidans/{id}
     */
    public function getBidanDetail(int $bidanId): JsonResponse
    {
        $bidan = User::where('user_id', $bidanId)
            ->where('role', 'bidan')
            ->where('is_active', true)
            ->first();

        if (!$bidan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bidan tidak ditemukan',
            ], 404);
        }

        // Get bidan's locations
        $locations = BidanLocation::where('bidan_id', $bidanId)
            ->where('is_active', true)
            ->get();

        // Get bidan profile if exists
        $profile = $bidan->bidanProfile;

        return response()->json([
            'status' => 'success',
            'data' => [
                'bidan_id' => $bidan->user_id,
                'name' => $bidan->name,
                'profile' => $profile ? [
                    'tempat_praktik' => $profile->tempat_praktik,
                    'alamat_praktik' => $profile->alamat_praktik,
                    'kota_tempat_praktik' => $profile->kota_tempat_praktik,
                    'telepon_tempat_praktik' => $profile->telepon_tempat_praktik,
                    'spesialisasi' => $profile->spesialisasi,
                    'photo' => $profile->photo,
                ] : null,
                'locations' => $locations->map(fn($loc) => [
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
                    'status' => $appointment->status,
                    'bidan_name' => $bidan->name,
                    'preferred_date' => $appointment->preferred_date,
                    'consent_id' => $consent->consent_id,
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

