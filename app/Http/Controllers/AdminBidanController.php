<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\BidanApplication;
use App\Models\BidanProfile;
use App\Models\BidanSubscription;
use App\Models\BidanLocation;
use App\Models\User;
use App\Support\AuthToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminBidanController extends Controller
{
    /**
     * Get all subscription plans (Admin)
     * GET /api/admin/subscription-plans
     */
    public function getPlans(Request $request): JsonResponse
    {
        $query = SubscriptionPlan::query();
        
        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $plans = $query->ordered()->get();

        return response()->json([
            'status' => 'success',
            'data' => $plans,
        ]);
    }

    /**
     * Create subscription plan (Admin)
     * POST /api/admin/subscription-plans
     */
    public function createPlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $plan = SubscriptionPlan::create([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'duration_days' => $request->duration_days,
            'features' => $request->features ?? [],
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Paket langganan berhasil dibuat',
            'data' => $plan,
        ], 201);
    }

    /**
     * Update subscription plan (Admin)
     * PUT /api/admin/subscription-plans/{id}
     */
    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $plan = SubscriptionPlan::find($id);
        
        if (!$plan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Paket langganan tidak ditemukan',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:100',
            'description' => 'nullable|string',
            'price' => 'numeric|min:0',
            'duration_days' => 'integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $plan->update($request->only([
            'name', 'description', 'price', 'duration_days', 
            'features', 'is_active', 'sort_order'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Paket langganan berhasil diperbarui',
            'data' => $plan->fresh(),
        ]);
    }

    /**
     * Format application payload consistently for admin frontend
     */
    protected function formatApplication(BidanApplication $app): array
    {
        $arr = $app->toArray();
        $arr['id'] = $app->bidan_application_id;
        $arr['nama'] = $app->full_name;
        $arr['practice_address'] = $app->full_address;
        $arr['plan_id'] = $app->subscription_plan_id;
        $arr['plan'] = $app->relationLoaded('subscriptionPlan') ? $app->subscriptionPlan : null;
        $arr['submitted_at'] = $app->created_at?->toIso8601String();
        $arr['reviewed_at'] = ($app->approved_at ?? $app->rejected_at)?->toIso8601String();
        $arr['province_name'] = $app->province;
        $arr['regency_name'] = $app->city;
        $arr['district_name'] = $app->district;
        return $arr;
    }

    /**
     * Get bidan applications (Admin)
     * GET /api/admin/bidan-applications
     */
    public function getApplications(Request $request): JsonResponse
    {
        $query = BidanApplication::with('subscriptionPlan');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('bidan_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $applications = $query->latest()->paginate($perPage);
        $applications->getCollection()->transform(fn ($app) => $this->formatApplication($app));

        return response()->json([
            'status' => 'success',
            'data' => $applications,
        ]);
    }

    /**
     * Get single application detail (Admin)
     * GET /api/admin/bidan-applications/{id}
     */
    public function getApplicationDetail(int $id): JsonResponse
    {
        $application = BidanApplication::with(['subscriptionPlan', 'approvedByAdmin'])
            ->find($id);

        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aplikasi tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatApplication($application),
        ]);
    }

    /**
     * Approve application (Admin)
     * PATCH /api/admin/bidan-applications/{id}/approve
     */
    public function approveApplication(Request $request, int $id): JsonResponse
    {
        $application = BidanApplication::find($id);

        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aplikasi tidak ditemukan',
            ], 404);
        }

        if (!$application->isPending()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aplikasi sudah diproses sebelumnya',
            ], 400);
        }

        [$adminId] = AuthToken::uidRoleOrFail($request);

        $application->update([
            'status' => 'approved',
            'approved_by_admin_id' => $adminId,
            'approved_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Aplikasi berhasil disetujui. Silakan buat akun bidan.',
            'data' => [
                'application_id' => $application->bidan_application_id,
                'status' => $application->status,
            ],
        ]);
    }

    /**
     * Reject application (Admin)
     * PATCH /api/admin/bidan-applications/{id}/reject
     */
    public function rejectApplication(Request $request, int $id): JsonResponse
    {
        $reason = $request->input('reason') ?? $request->input('rejection_reason');
        $validator = Validator::make(['reason' => $reason], [
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $application = BidanApplication::find($id);

        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aplikasi tidak ditemukan',
            ], 404);
        }

        if (!$application->isPending()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aplikasi sudah diproses sebelumnya',
            ], 400);
        }

        [$adminId] = AuthToken::uidRoleOrFail($request);

        $application->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by_admin_id' => $adminId,
            'rejected_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Aplikasi berhasil ditolak',
            'data' => [
                'id' => $application->bidan_application_id,
                'application_id' => $application->bidan_application_id,
                'status' => $application->status,
            ],
        ]);
    }

    /**
     * Create bidan account from approved application (Admin)
     * POST /api/admin/bidans
     */
    public function createBidanAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'application_id' => 'required|exists:bidan_applications,bidan_application_id',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ], [
            'application_id.required' => 'ID aplikasi wajib diisi',
            'application_id.exists' => 'Aplikasi tidak ditemukan',
            'email.required' => 'Email wajib diisi',
            'email.unique' => 'Email sudah terdaftar',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal 8 karakter',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $application = BidanApplication::find($request->application_id);

        if (!$application->isApproved()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aplikasi belum disetujui',
            ], 400);
        }

        // Check if subscription already created
        $existingSubscription = BidanSubscription::where('bidan_application_id', $application->bidan_application_id)
            ->first();

        if ($existingSubscription) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun bidan sudah dibuat untuk aplikasi ini',
            ], 409);
        }

        try {
            DB::beginTransaction();

            // Create user account (pre-verified since created by Admin from approved application)
            $user = User::create([
                'name' => $application->full_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'bidan',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            // Get plan for subscription duration
            $plan = $application->subscriptionPlan;

            // Create subscription
            $subscription = BidanSubscription::create([
                'user_id' => $user->user_id,
                'bidan_application_id' => $application->bidan_application_id,
                'subscription_plan_id' => $plan->subscription_plan_id,
                'start_date' => Carbon::today(),
                'end_date' => Carbon::today()->addDays($plan->duration_days),
                'status' => 'active',
                'amount_paid' => $plan->price,
            ]);

            // Auto-create verified BidanProfile from approved application
            BidanProfile::create([
                'user_id' => $user->user_id,
                'photo' => '',
                'tempat_praktik' => $application->bidan_name,
                'alamat_praktik' => $application->full_address,
                'provinsi_tempat_praktik' => $application->province,
                'province_code' => $application->province_code,
                'kota_tempat_praktik' => $application->city,
                'regency_code' => $application->regency_code,
                'kecamatan_tempat_praktik' => $application->district,
                'district_code' => $application->district_code,
                'telepon_tempat_praktik' => $application->phone,
                'spesialisasi' => $application->spesialisasi,
                'str_number' => $application->str_number,
                'sip_number' => $application->sip_number,
                'is_verified_partner' => true,
                'verified_at' => now(),
            ]);

            // Auto-create primary BidanLocation if application includes coordinates
            $location = null;
            if ($application->lat !== null && $application->lng !== null) {
                $location = BidanLocation::create([
                    'bidan_id' => $user->user_id,
                    'lat' => $application->lat,
                    'lng' => $application->lng,
                    'address_label' => $application->full_address,
                    'phone_override' => $application->phone,
                    'operating_hours' => $application->operating_hours,
                    'is_active' => true,
                    'is_primary' => true,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Akun bidan berhasil dibuat',
                'data' => [
                    'user_id' => $user->user_id,
                    'bidan_id' => $user->user_id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'has_location' => $location !== null,
                    'location_id' => $location?->bidan_location_id,
                    'subscription' => [
                        'id' => $subscription->bidan_subscription_id,
                        'plan' => $plan->name,
                        'start_date' => $subscription->start_date,
                        'end_date' => $subscription->end_date,
                    ],
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membuat akun bidan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all bidan accounts (Admin)
     * GET /api/admin/bidans
     */
    public function getBidans(Request $request): JsonResponse
    {
        $query = User::where('role', 'bidan')
            ->with(['bidanSubscription.subscriptionPlan', 'bidanLocations']);

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by location existence
        if ($request->has('has_location')) {
            if ($request->boolean('has_location')) {
                $query->whereHas('bidanLocations');
            } else {
                $query->whereDoesntHave('bidanLocations');
            }
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $bidans = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $bidans,
        ]);
    }

    /**
     * Update bidan status (Admin)
     * PATCH /api/admin/bidans/{id}/status
     */
    public function updateBidanStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bidan = User::where('user_id', $id)->where('role', 'bidan')->first();

        if (!$bidan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bidan tidak ditemukan',
            ], 404);
        }

        $bidan->update(['is_active' => $request->boolean('is_active')]);

        return response()->json([
            'status' => 'success',
            'message' => $request->boolean('is_active') ? 'Akun bidan diaktifkan' : 'Akun bidan dinonaktifkan',
            'data' => [
                'user_id' => $bidan->user_id,
                'is_active' => $bidan->is_active,
            ],
        ]);
    }

    /**
     * Create/Update bidan location (Admin)
     * POST /api/admin/bidans/{id}/location
     */
    public function setBidanLocation(Request $request, int $bidanId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'address_label' => 'required|string|max:255',
            'phone_override' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'operating_hours' => 'nullable|array',
            'is_primary' => 'boolean',
        ], [
            'lat.required' => 'Latitude wajib diisi',
            'lat.between' => 'Latitude harus antara -90 dan 90',
            'lng.required' => 'Longitude wajib diisi',
            'lng.between' => 'Longitude harus antara -180 dan 180',
            'address_label.required' => 'Label alamat wajib diisi',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bidan = User::where('user_id', $bidanId)->where('role', 'bidan')->first();

        if (!$bidan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bidan tidak ditemukan',
            ], 404);
        }

        // If setting as primary, unset other primary locations
        if ($request->boolean('is_primary')) {
            BidanLocation::where('bidan_id', $bidanId)
                ->update(['is_primary' => false]);
        }

        $location = BidanLocation::updateOrCreate(
            [
                'bidan_id' => $bidanId,
                'is_primary' => true, // Update primary location
            ],
            [
                'lat' => $request->lat,
                'lng' => $request->lng,
                'address_label' => $request->address_label,
                'phone_override' => $request->phone_override,
                'notes' => $request->notes,
                'operating_hours' => $request->operating_hours,
                'is_active' => true,
                'is_primary' => $request->boolean('is_primary', true),
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Lokasi bidan berhasil disimpan',
            'data' => $location,
        ]);
    }

    /**
     * Get all bidan locations (Admin)
     * GET /api/admin/bidan-locations
     */
    public function getBidanLocations(Request $request): JsonResponse
    {
        $query = BidanLocation::with(['bidan.bidanProfile']);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('bidan_id')) {
            $query->where('bidan_id', $request->bidan_id);
        }

        $perPage = $request->get('per_page', 50);
        $locations = $query->latest()->paginate($perPage);

        $locations->getCollection()->transform(function ($loc) {
            $arr = $loc->toArray();
            $arr['id'] = $loc->bidan_location_id;
            $arr['lat'] = (float) $loc->lat;
            $arr['lng'] = (float) $loc->lng;
            $arr['latitude'] = (float) $loc->lat;
            $arr['longitude'] = (float) $loc->lng;
            if ($loc->bidan) {
                $arr['bidan']['id'] = $loc->bidan->user_id;
                $arr['bidan']['bidan_name'] = $loc->bidan->bidanProfile?->tempat_praktik ?: $loc->bidan->name;
            }
            return $arr;
        });

        return response()->json([
            'status' => 'success',
            'data' => $locations,
        ]);
    }

    /**
     * Update bidan location (Admin)
     * PUT /api/admin/bidan-locations/{id}
     */
    public function updateBidanLocation(Request $request, int $locationId): JsonResponse
    {
        $location = BidanLocation::find($locationId);

        if (!$location) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lokasi tidak ditemukan',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'lat' => 'numeric|between:-90,90',
            'lng' => 'numeric|between:-180,180',
            'address_label' => 'string|max:255',
            'phone_override' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'operating_hours' => 'nullable|array',
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // If setting as primary, unset other primary locations
        if ($request->has('is_primary') && $request->boolean('is_primary')) {
            BidanLocation::where('bidan_id', $location->bidan_id)
                ->where('bidan_location_id', '!=', $locationId)
                ->update(['is_primary' => false]);
        }

        $location->update($request->only([
            'lat', 'lng', 'address_label', 'phone_override', 
            'notes', 'operating_hours', 'is_active', 'is_primary'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Lokasi berhasil diperbarui',
            'data' => $location->fresh(),
        ]);
    }

    /**
     * Toggle location active status (Admin)
     * PATCH /api/admin/bidan-locations/{id}/toggle-active
     */
    public function toggleLocationActive(int $locationId): JsonResponse
    {
        $location = BidanLocation::find($locationId);

        if (!$location) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lokasi tidak ditemukan',
            ], 404);
        }

        $location->update(['is_active' => !$location->is_active]);

        return response()->json([
            'status' => 'success',
            'message' => $location->is_active ? 'Lokasi diaktifkan' : 'Lokasi dinonaktifkan',
            'data' => [
                'location_id' => $location->bidan_location_id,
                'is_active' => $location->is_active,
            ],
        ]);
    }
}
