<?php

namespace App\Http\Controllers;

use App\Models\BidanSubscription;
use App\Models\BidanLocation;
use App\Models\Appointment;
use App\Support\AuthToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class BidanDashboardController extends Controller
{
    /**
     * Get bidan profile and subscription info
     * GET /api/bidan/me
     */
    public function me(Request $request): JsonResponse
    {
        [$userId, $role] = AuthToken::uidRoleOrFail($request);

        if ($role !== 'bidan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak',
            ], 403);
        }

        $subscription = BidanSubscription::with('subscriptionPlan')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        $locations = BidanLocation::where('bidan_id', $userId)
            ->where('is_active', true)
            ->get();

        $appointmentStats = [
            'requested' => Appointment::where('bidan_id', $userId)->where('status', 'requested')->count(),
            'accepted' => Appointment::where('bidan_id', $userId)->where('status', 'accepted')->count(),
            'completed' => Appointment::where('bidan_id', $userId)->where('status', 'completed')->count(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'subscription' => $subscription ? [
                    'id' => $subscription->bidan_subscription_id,
                    'plan' => $subscription->subscriptionPlan,
                    'start_date' => $subscription->start_date,
                    'end_date' => $subscription->end_date,
                    'remaining_days' => $subscription->remaining_days,
                    'status' => $subscription->status,
                    'is_active' => $subscription->isActive(),
                ] : null,
                'locations' => $locations,
                'appointment_stats' => $appointmentStats,
            ],
        ]);
    }

    /**
     * Update bidan profile
     * PUT /api/bidan/me
     */
    public function updateProfile(Request $request): JsonResponse
    {
        [$userId, $role] = AuthToken::uidRoleOrFail($request);

        if ($role !== 'bidan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update via bidan_profile table if exists
        // This is a simplified version - extend as needed

        return response()->json([
            'status' => 'success',
            'message' => 'Profil berhasil diperbarui',
        ]);
    }

    /**
     * Get appointments for bidan
     * GET /api/bidan/appointments
     */
    public function getAppointments(Request $request): JsonResponse
    {
        [$userId, $role] = AuthToken::uidRoleOrFail($request);

        if ($role !== 'bidan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak',
            ], 403);
        }

        $query = Appointment::with(['user', 'location', 'consent'])
            ->where('bidan_id', $userId);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('preferred_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('preferred_date', '<=', $request->to_date);
        }

        $perPage = $request->get('per_page', 15);
        $appointments = $query->latest()->paginate($perPage);

        // Transform to include user data based on consent
        $appointments->getCollection()->transform(function ($appointment) {
            $userData = $appointment->getUserDataWithConsent();
            $appointment->user_data = $userData;
            unset($appointment->user); // Hide raw user data
            return $appointment;
        });

        return response()->json([
            'status' => 'success',
            'data' => $appointments,
        ]);
    }

    /**
     * Get single appointment detail
     * GET /api/bidan/appointments/{id}
     */
    public function getAppointmentDetail(Request $request, int $id): JsonResponse
    {
        [$userId, $role] = AuthToken::uidRoleOrFail($request);

        if ($role !== 'bidan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak',
            ], 403);
        }

        $appointment = Appointment::with(['location', 'consent'])
            ->where('bidan_id', $userId)
            ->where('appointment_id', $id)
            ->first();

        if (!$appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment tidak ditemukan',
            ], 404);
        }

        // Get user data based on consent
        $userData = $appointment->getUserDataWithConsent();

        return response()->json([
            'status' => 'success',
            'data' => [
                'appointment' => $appointment,
                'user_data' => $userData,
            ],
        ]);
    }

    /**
     * Accept appointment
     * PATCH /api/bidan/appointments/{id}/accept
     */
    public function acceptAppointment(Request $request, int $id): JsonResponse
    {
        [$userId, $role] = AuthToken::uidRoleOrFail($request);

        if ($role !== 'bidan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak',
            ], 403);
        }

        $appointment = Appointment::where('bidan_id', $userId)
            ->where('appointment_id', $id)
            ->first();

        if (!$appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment tidak ditemukan',
            ], 404);
        }

        if (!$appointment->isPending()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment sudah diproses',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'confirmed_date' => 'nullable|date',
            'confirmed_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $appointment->accept(
            $request->confirmed_date,
            $request->confirmed_time,
            $request->notes
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Appointment berhasil diterima',
            'data' => $appointment->fresh(),
        ]);
    }

    /**
     * Reject appointment
     * PATCH /api/bidan/appointments/{id}/reject
     */
    public function rejectAppointment(Request $request, int $id): JsonResponse
    {
        [$userId, $role] = AuthToken::uidRoleOrFail($request);

        if ($role !== 'bidan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak',
            ], 403);
        }

        $appointment = Appointment::where('bidan_id', $userId)
            ->where('appointment_id', $id)
            ->first();

        if (!$appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment tidak ditemukan',
            ], 404);
        }

        if (!$appointment->isPending()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment sudah diproses',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $appointment->reject($request->reason);

        return response()->json([
            'status' => 'success',
            'message' => 'Appointment berhasil ditolak',
            'data' => $appointment->fresh(),
        ]);
    }

    /**
     * Complete appointment
     * PATCH /api/bidan/appointments/{id}/complete
     */
    public function completeAppointment(Request $request, int $id): JsonResponse
    {
        [$userId, $role] = AuthToken::uidRoleOrFail($request);

        if ($role !== 'bidan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak',
            ], 403);
        }

        $appointment = Appointment::where('bidan_id', $userId)
            ->where('appointment_id', $id)
            ->first();

        if (!$appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment tidak ditemukan',
            ], 404);
        }

        if (!$appointment->isAccepted()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment belum diterima atau sudah selesai',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $appointment->complete($request->notes);

        return response()->json([
            'status' => 'success',
            'message' => 'Appointment berhasil diselesaikan',
            'data' => $appointment->fresh(),
        ]);
    }

    /**
     * Reschedule appointment (bidan proposes new date/time)
     * PATCH /api/bidan/appointments/{id}/reschedule
     */
    public function rescheduleAppointment(Request $request, int $id): JsonResponse
    {
        [$userId, $role] = AuthToken::uidRoleOrFail($request);

        if ($role !== 'bidan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak',
            ], 403);
        }

        $appointment = Appointment::where('bidan_id', $userId)
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
            'confirmed_date' => 'required|date|after_or_equal:today',
            'confirmed_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $appointment->reschedule(
            $request->confirmed_date,
            $request->confirmed_time,
            'bidan'
        );

        if ($request->filled('notes')) {
            $appointment->update(['bidan_notes' => $request->notes]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Appointment berhasil dijadwalkan ulang',
            'data' => $appointment->fresh(),
        ]);
    }
}

