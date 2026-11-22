<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\SubscriptionPlan;
use App\Models\BidanApplication;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    /**
     * Get all active subscription plans (Public)
     * GET /api/public/subscription-plans
     */
    public function getPlans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->ordered()
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Subscription plans retrieved successfully',
            'data' => $plans,
        ]);
    }

    /**
     * Submit bidan application (Public)
     * POST /api/public/bidan-applications
     */
    public function submitApplication(Request $request): JsonResponse
    {
        // Support operating_hours sent as JSON string (from multipart/form-data)
        $input = $request->all();
        if (isset($input['operating_hours']) && is_string($input['operating_hours'])) {
            $decoded = json_decode($input['operating_hours'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $input['operating_hours'] = $decoded;
            }
        }

        $validator = Validator::make($input, [
            'subscription_plan_id' => 'required|exists:subscription_plans,subscription_plan_id',
            'full_name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:20',
            'bidan_name' => 'required|string|max:150',
            'spesialisasi' => 'nullable|string|max:255',
            'full_address' => 'required|string',
            'province_code' => 'required|string|exists:provinces,code',
            'regency_code' => 'required|string|exists:regencies,code',
            'district_code' => 'required|string|exists:districts,code',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'operating_hours' => 'nullable|array',
            'str_number' => 'required|string|max:100',
            'sip_number' => 'required|string|max:100',
            'document_file' => 'required_without:document_url|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
            'document_url' => 'required_without:document_file|nullable|url|max:500',
        ], [
            'subscription_plan_id.required' => 'Pilih paket langganan terlebih dahulu',
            'subscription_plan_id.exists' => 'Paket langganan tidak valid',
            'full_name.required' => 'Nama lengkap wajib diisi',
            'email.required' => 'Email wajib diisi',
            'email.email' => 'Format email tidak valid',
            'phone.required' => 'Nomor telepon wajib diisi',
            'bidan_name.required' => 'Nama praktik bidan wajib diisi',
            'full_address.required' => 'Alamat lengkap wajib diisi',
            'province_code.required' => 'Provinsi wajib dipilih',
            'regency_code.required' => 'Kabupaten/Kota wajib dipilih',
            'district_code.required' => 'Kecamatan wajib dipilih',
            'lat.required' => 'Titik koordinat lokasi praktik pada peta wajib dipilih',
            'lng.required' => 'Titik koordinat lokasi praktik pada peta wajib dipilih',
            'str_number.required' => 'Nomor STR wajib diisi',
            'sip_number.required' => 'Nomor SIP/SIPB wajib diisi',
            'document_file.required_without' => 'Dokumen izin/sertifikat (STR/SIP) wajib diunggah',
            'document_file.mimes' => 'Dokumen harus berupa file PDF, JPG, PNG, atau WEBP',
            'document_file.max' => 'Ukuran file dokumen maksimal 5MB',
            'document_url.required_without' => 'Dokumen izin/sertifikat (STR/SIP) wajib dilampirkan',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify region hierarchy consistency
        $province = Province::find($input['province_code']);
        $regency = Regency::where('code', $input['regency_code'])
            ->where('province_code', $input['province_code'])
            ->first();
        if (!$regency) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kabupaten/Kota tidak sesuai dengan Provinsi yang dipilih',
            ], 422);
        }

        $district = District::where('code', $input['district_code'])
            ->where('regency_code', $input['regency_code'])
            ->first();
        if (!$district) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kecamatan tidak sesuai dengan Kabupaten/Kota yang dipilih',
            ], 422);
        }

        // Check if email already has pending application
        $existingApplication = BidanApplication::where('email', $request->email)
            ->where('status', 'pending')
            ->first();

        if ($existingApplication) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email sudah memiliki pengajuan yang sedang diproses',
            ], 409);
        }

        // Check if plan is active
        $plan = SubscriptionPlan::find($request->subscription_plan_id);
        if (!$plan || !$plan->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Paket langganan tidak tersedia',
            ], 400);
        }

        // Handle certificate/license document upload
        $documentUrl = $request->input('document_url');
        if ($request->hasFile('document_file')) {
            $storedPath = $request->file('document_file')->store('bidan-documents', 'public');
            $documentUrl = url('storage/' . $storedPath);
        }

        $application = BidanApplication::create([
            'subscription_plan_id' => $request->subscription_plan_id,
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'bidan_name' => $request->bidan_name,
            'spesialisasi' => $request->spesialisasi,
            'full_address' => $request->full_address,
            'province' => $province?->name,
            'province_code' => $province?->code,
            'city' => $regency->name,
            'regency_code' => $regency->code,
            'district' => $district->name,
            'district_code' => $district->code,
            'lat' => (float) $input['lat'],
            'lng' => (float) $input['lng'],
            'operating_hours' => $input['operating_hours'] ?? null,
            'str_number' => $request->str_number,
            'sip_number' => $request->sip_number,
            'document_url' => $documentUrl,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan kemitraan berhasil dikirim. Tim kami akan memverifikasi izin dan dokumen Anda.',
            'data' => [
                'id' => $application->bidan_application_id,
                'application_id' => $application->bidan_application_id,
                'status' => $application->status,
                'bidan_name' => $application->bidan_name,
                'province' => $application->province,
                'city' => $application->city,
                'district' => $application->district,
                'document_url' => $application->document_url,
                'submitted_at' => $application->created_at,
            ],
        ], 201);
    }

    /**
     * Check application status by email (Public)
     * GET /api/public/bidan-applications/status?email=xxx
     */
    public function checkApplicationStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email tidak valid',
            ], 422);
        }

        $application = BidanApplication::with('subscriptionPlan')
            ->where('email', $request->email)
            ->latest()
            ->first();

        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ditemukan pengajuan dengan email tersebut',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'application_id' => $application->bidan_application_id,
                'status' => $application->status,
                'full_name' => $application->full_name,
                'bidan_name' => $application->bidan_name,
                'plan_name' => $application->subscriptionPlan?->name,
                'province' => $application->province,
                'city' => $application->city,
                'district' => $application->district,
                'submitted_at' => $application->created_at,
                'approved_at' => $application->approved_at,
                'rejected_at' => $application->rejected_at,
                'rejection_reason' => $application->rejection_reason,
            ],
        ]);
    }
}
