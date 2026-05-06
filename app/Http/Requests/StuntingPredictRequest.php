<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StuntingPredictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by route middleware
    }

    public function rules(): array
    {
        return [
            'child_gender'        => 'required|string|in:male,female',
            'mother_education'    => 'required|string|in:tidak_sekolah,sd,smp,sma,diploma,sarjana',
            'mother_employment'   => 'required|string|in:working,not_working',
            'mother_height_cm'    => 'required|numeric|min:100|max:200',
            'mother_age_at_birth' => 'required|integer|min:12|max:55',
            'water_access'        => 'required|string|in:safe,unsafe',
            'sanitation_access'   => 'required|string|in:proper,improper',
            'home_ownership'      => 'required|string|in:owned,rented',
            'has_electricity'     => 'required|boolean',
            'has_refrigerator'    => 'required|boolean',
            'has_tv'              => 'required|boolean',
            'delivery_insurance'  => 'required|boolean',
            'anc_place'           => 'required|string|in:clinic_midwife,hospital,traditional_other,unknown',
        ];
    }

    public function messages(): array
    {
        return [
            'child_gender.required'        => 'Jenis kelamin anak wajib diisi.',
            'child_gender.in'              => 'Jenis kelamin anak harus "male" atau "female".',
            'mother_education.required'    => 'Pendidikan ibu wajib diisi.',
            'mother_education.in'          => 'Pendidikan ibu harus salah satu: tidak_sekolah, sd, smp, sma, diploma, sarjana.',
            'mother_employment.required'   => 'Status pekerjaan ibu wajib diisi.',
            'mother_employment.in'         => 'Status pekerjaan ibu harus "working" atau "not_working".',
            'mother_height_cm.required'    => 'Tinggi badan ibu wajib diisi.',
            'mother_height_cm.min'         => 'Tinggi badan ibu minimal 100 cm.',
            'mother_height_cm.max'         => 'Tinggi badan ibu maksimal 200 cm.',
            'mother_age_at_birth.required' => 'Usia ibu saat melahirkan wajib diisi.',
            'mother_age_at_birth.min'      => 'Usia ibu saat melahirkan minimal 12 tahun.',
            'mother_age_at_birth.max'      => 'Usia ibu saat melahirkan maksimal 55 tahun.',
            'water_access.in'              => 'Akses air harus "safe" atau "unsafe".',
            'sanitation_access.in'         => 'Akses sanitasi harus "proper" atau "improper".',
            'home_ownership.in'            => 'Kepemilikan rumah harus "owned" atau "rented".',
            'anc_place.in'                 => 'Tempat ANC harus salah satu: clinic_midwife, hospital, traditional_other, unknown.',
        ];
    }

    /**
     * Return validation errors as JSON (API-friendly).
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
