<?php

namespace Tests\Unit;

use App\Helpers\StuntingMapper;
use PHPUnit\Framework\TestCase;

class StuntingMapperTest extends TestCase
{
    /**
     * Helper: complete valid humanized input.
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'child_gender'        => 'male',
            'mother_education'    => 'sma',
            'mother_employment'   => 'working',
            'mother_height_cm'    => 155,
            'mother_age_at_birth' => 24,
            'water_access'        => 'safe',
            'sanitation_access'   => 'proper',
            'home_ownership'      => 'owned',
            'has_electricity'     => true,
            'has_refrigerator'    => false,
            'has_tv'              => true,
            'delivery_insurance'  => true,
            'anc_place'           => 'clinic_midwife',
        ], $overrides);
    }

    // ─── Payload Structure ──────────────────────────────────

    public function test_payload_has_exactly_18_features(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput());

        $this->assertCount(18, $payload);
    }

    public function test_payload_contains_all_required_ml_keys(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput());

        $expectedKeys = [
            'child_gender',
            'mother_education_level',
            'mother_employment_status',
            'mother_height_cm',
            'improved_water',
            'improved_sanitation',
            'home_ownership',
            'has_electricity',
            'has_refrigerator',
            'has_tv',
            'mother_age_at_birth',
            'is_teenage_mother',
            'is_high_risk_mother_age',
            'has_delivery_insurance',
            'anc_clinic_midwife',
            'anc_hospital',
            'anc_traditional_other',
            'anc_unknown',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $payload, "Missing key: {$key}");
        }
    }

    // ─── Gender Mapping ─────────────────────────────────────

    public function test_male_maps_to_1(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['child_gender' => 'male']));

        $this->assertSame(1, $payload['child_gender']);
    }

    public function test_female_maps_to_0(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['child_gender' => 'female']));

        $this->assertSame(0, $payload['child_gender']);
    }

    // ─── Education Mapping ──────────────────────────────────

    /**
     * @dataProvider educationProvider
     */
    public function test_education_mapping(string $level, int $expected): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['mother_education' => $level]));

        $this->assertSame($expected, $payload['mother_education_level']);
    }

    public static function educationProvider(): array
    {
        return [
            'tidak_sekolah' => ['tidak_sekolah', 1],
            'sd'            => ['sd', 2],
            'smp'           => ['smp', 3],
            'sma'           => ['sma', 4],
            'diploma'       => ['diploma', 5],
            'sarjana'       => ['sarjana', 6],
        ];
    }

    // ─── Employment Mapping ─────────────────────────────────

    public function test_working_maps_to_1(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['mother_employment' => 'working']));

        $this->assertSame(1, $payload['mother_employment_status']);
    }

    public function test_not_working_maps_to_0(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['mother_employment' => 'not_working']));

        $this->assertSame(0, $payload['mother_employment_status']);
    }

    // ─── Height Mapping ─────────────────────────────────────

    public function test_height_preserves_as_float(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['mother_height_cm' => 155]));

        $this->assertSame(155.0, $payload['mother_height_cm']);
        $this->assertIsFloat($payload['mother_height_cm']);
    }

    // ─── Binary Feature Mappings ────────────────────────────

    public function test_safe_water_maps_to_1(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['water_access' => 'safe']));

        $this->assertSame(1, $payload['improved_water']);
    }

    public function test_unsafe_water_maps_to_0(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['water_access' => 'unsafe']));

        $this->assertSame(0, $payload['improved_water']);
    }

    public function test_proper_sanitation_maps_to_1(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['sanitation_access' => 'proper']));

        $this->assertSame(1, $payload['improved_sanitation']);
    }

    public function test_improper_sanitation_maps_to_0(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['sanitation_access' => 'improper']));

        $this->assertSame(0, $payload['improved_sanitation']);
    }

    public function test_owned_home_maps_to_1(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['home_ownership' => 'owned']));

        $this->assertSame(1, $payload['home_ownership']);
    }

    public function test_rented_home_maps_to_0(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['home_ownership' => 'rented']));

        $this->assertSame(0, $payload['home_ownership']);
    }

    // ─── Boolean Mappings ───────────────────────────────────

    public function test_boolean_true_maps_to_1(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput([
            'has_electricity'    => true,
            'has_refrigerator'   => true,
            'has_tv'             => true,
            'delivery_insurance' => true,
        ]));

        $this->assertSame(1, $payload['has_electricity']);
        $this->assertSame(1, $payload['has_refrigerator']);
        $this->assertSame(1, $payload['has_tv']);
        $this->assertSame(1, $payload['has_delivery_insurance']);
    }

    public function test_boolean_false_maps_to_0(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput([
            'has_electricity'    => false,
            'has_refrigerator'   => false,
            'has_tv'             => false,
            'delivery_insurance' => false,
        ]));

        $this->assertSame(0, $payload['has_electricity']);
        $this->assertSame(0, $payload['has_refrigerator']);
        $this->assertSame(0, $payload['has_tv']);
        $this->assertSame(0, $payload['has_delivery_insurance']);
    }

    // ─── Derived: Teenage Mother ────────────────────────────

    public function test_age_17_is_teenage_mother(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['mother_age_at_birth' => 17]));

        $this->assertSame(1, $payload['is_teenage_mother']);
    }

    public function test_age_19_is_teenage_mother(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['mother_age_at_birth' => 19]));

        $this->assertSame(1, $payload['is_teenage_mother']);
    }

    public function test_age_20_is_not_teenage_mother(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['mother_age_at_birth' => 20]));

        $this->assertSame(0, $payload['is_teenage_mother']);
    }

    public function test_age_24_is_not_teenage_mother(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['mother_age_at_birth' => 24]));

        $this->assertSame(0, $payload['is_teenage_mother']);
    }

    // ─── Derived: High Risk Age ─────────────────────────────

    public function test_age_17_is_high_risk(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['mother_age_at_birth' => 17]));

        $this->assertSame(1, $payload['is_high_risk_mother_age']);
    }

    public function test_age_18_is_not_high_risk(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['mother_age_at_birth' => 18]));

        $this->assertSame(0, $payload['is_high_risk_mother_age']);
    }

    public function test_age_35_is_not_high_risk(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['mother_age_at_birth' => 35]));

        $this->assertSame(0, $payload['is_high_risk_mother_age']);
    }

    public function test_age_36_is_high_risk(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['mother_age_at_birth' => 36]));

        $this->assertSame(1, $payload['is_high_risk_mother_age']);
    }

    public function test_age_25_is_safe_range(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['mother_age_at_birth' => 25]));

        $this->assertSame(0, $payload['is_teenage_mother']);
        $this->assertSame(0, $payload['is_high_risk_mother_age']);
    }

    // ─── ANC One-Hot Encoding ───────────────────────────────

    public function test_anc_clinic_midwife_one_hot(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['anc_place' => 'clinic_midwife']));

        $this->assertSame(1, $payload['anc_clinic_midwife']);
        $this->assertSame(0, $payload['anc_hospital']);
        $this->assertSame(0, $payload['anc_traditional_other']);
        $this->assertSame(0, $payload['anc_unknown']);
    }

    public function test_anc_hospital_one_hot(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['anc_place' => 'hospital']));

        $this->assertSame(0, $payload['anc_clinic_midwife']);
        $this->assertSame(1, $payload['anc_hospital']);
        $this->assertSame(0, $payload['anc_traditional_other']);
        $this->assertSame(0, $payload['anc_unknown']);
    }

    public function test_anc_traditional_other_one_hot(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['anc_place' => 'traditional_other']));

        $this->assertSame(0, $payload['anc_clinic_midwife']);
        $this->assertSame(0, $payload['anc_hospital']);
        $this->assertSame(1, $payload['anc_traditional_other']);
        $this->assertSame(0, $payload['anc_unknown']);
    }

    public function test_anc_unknown_one_hot(): void
    {
        $payload = StuntingMapper::toMlPayload($this->validInput(['anc_place' => 'unknown']));

        $this->assertSame(0, $payload['anc_clinic_midwife']);
        $this->assertSame(0, $payload['anc_hospital']);
        $this->assertSame(0, $payload['anc_traditional_other']);
        $this->assertSame(1, $payload['anc_unknown']);
    }

    // ─── Full Scenario: Complete Mapping ────────────────────

    public function test_complete_mapping_scenario(): void
    {
        $payload = StuntingMapper::toMlPayload([
            'child_gender'        => 'female',
            'mother_education'    => 'sarjana',
            'mother_employment'   => 'not_working',
            'mother_height_cm'    => 162.5,
            'mother_age_at_birth' => 36,
            'water_access'        => 'unsafe',
            'sanitation_access'   => 'improper',
            'home_ownership'      => 'rented',
            'has_electricity'     => false,
            'has_refrigerator'    => true,
            'has_tv'              => false,
            'delivery_insurance'  => false,
            'anc_place'           => 'hospital',
        ]);

        $this->assertSame(0, $payload['child_gender']);           // female
        $this->assertSame(6, $payload['mother_education_level']); // sarjana
        $this->assertSame(0, $payload['mother_employment_status']); // not_working
        $this->assertSame(162.5, $payload['mother_height_cm']);
        $this->assertSame(0, $payload['improved_water']);          // unsafe
        $this->assertSame(0, $payload['improved_sanitation']);     // improper
        $this->assertSame(0, $payload['home_ownership']);          // rented
        $this->assertSame(0, $payload['has_electricity']);
        $this->assertSame(1, $payload['has_refrigerator']);
        $this->assertSame(0, $payload['has_tv']);
        $this->assertSame(36, $payload['mother_age_at_birth']);
        $this->assertSame(0, $payload['is_teenage_mother']);       // 36 >= 20
        $this->assertSame(1, $payload['is_high_risk_mother_age']); // 36 > 35
        $this->assertSame(0, $payload['has_delivery_insurance']);
        $this->assertSame(0, $payload['anc_clinic_midwife']);
        $this->assertSame(1, $payload['anc_hospital']);
        $this->assertSame(0, $payload['anc_traditional_other']);
        $this->assertSame(0, $payload['anc_unknown']);
    }
}
