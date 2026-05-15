<?php

namespace App\Helpers;

/**
 * Maps humanized mobile input → ML-ready numeric payload.
 *
 * Encoding rules follow the IFLS5-based feature engineering:
 * - Binary flags: 1 = positive/present, 0 = negative/absent
 * - Education: ordinal scale 1–6
 * - ANC place: one-hot encoded into 4 columns
 * - Derived flags: is_teenage_mother, is_high_risk_mother_age
 */
class StuntingMapper
{
    /**
     * Education level ordinal mapping (IFLS5 convention).
     */
    private const EDUCATION_MAP = [
        'tidak_sekolah' => 1,
        'sd'            => 2,
        'smp'           => 3,
        'sma'           => 4,
        'diploma'       => 5,
        'sarjana'       => 6,
    ];

    /**
     * Transform humanized input into the 18-feature ML payload.
     */
    public static function toMlPayload(array $input): array
    {
        $age = (int) $input['mother_age_at_birth'];

        return [
            'child_gender'             => self::genderToNumeric($input['child_gender']),
            'mother_education_level'   => self::educationToNumeric($input['mother_education']),
            'mother_employment_status' => self::employmentToNumeric($input['mother_employment']),
            'mother_height_cm'         => (float) $input['mother_height_cm'],
            'improved_water'           => self::waterToNumeric($input['water_access']),
            'improved_sanitation'      => self::sanitationToNumeric($input['sanitation_access']),
            'home_ownership'           => self::ownershipToNumeric($input['home_ownership']),
            'has_electricity'          => self::boolToInt($input['has_electricity']),
            'has_refrigerator'         => self::boolToInt($input['has_refrigerator']),
            'has_tv'                   => self::boolToInt($input['has_tv']),
            'mother_age_at_birth'      => $age,
            'is_teenage_mother'        => self::isTeenageMother($age),
            'is_high_risk_mother_age'  => self::isHighRiskAge($age),
            'has_delivery_insurance'   => self::boolToInt($input['delivery_insurance']),
            ...self::ancOneHot($input['anc_place']),
        ];
    }

    // ─── Individual Mapping Functions ─────────────────────

    private static function genderToNumeric(string $gender): int
    {
        return $gender === 'male' ? 1 : 0;
    }

    private static function educationToNumeric(string $education): int
    {
        return self::EDUCATION_MAP[$education] ?? 1;
    }

    private static function employmentToNumeric(string $employment): int
    {
        return $employment === 'working' ? 1 : 0;
    }

    private static function waterToNumeric(string $water): int
    {
        return $water === 'safe' ? 1 : 0;
    }

    private static function sanitationToNumeric(string $sanitation): int
    {
        return $sanitation === 'proper' ? 1 : 0;
    }

    private static function ownershipToNumeric(string $ownership): int
    {
        return $ownership === 'owned' ? 1 : 0;
    }

    private static function boolToInt($value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    /**
     * Derived: teenage mother (< 20 years old at birth).
     */
    private static function isTeenageMother(int $age): int
    {
        return $age < 20 ? 1 : 0;
    }

    /**
     * Derived: high-risk age (< 18 or > 35).
     */
    private static function isHighRiskAge(int $age): int
    {
        return ($age < 18 || $age > 35) ? 1 : 0;
    }

    /**
     * One-hot encode ANC (Antenatal Care) place.
     */
    private static function ancOneHot(string $ancPlace): array
    {
        return [
            'anc_clinic_midwife'    => $ancPlace === 'clinic_midwife'    ? 1 : 0,
            'anc_hospital'          => $ancPlace === 'hospital'          ? 1 : 0,
            'anc_traditional_other' => $ancPlace === 'traditional_other' ? 1 : 0,
            'anc_unknown'           => $ancPlace === 'unknown'           ? 1 : 0,
        ];
    }
}
