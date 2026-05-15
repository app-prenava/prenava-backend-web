<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Helpers\JwtTestHelpers;
use App\Models\User;
use App\Models\StuntingPrediction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class StuntingPredictionTest extends TestCase
{
    use RefreshDatabase, JwtTestHelpers;

    private function validPayload(array $overrides = []): array
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

    private function fakeMlSuccess(): void
    {
        Http::fake([
            '*/predict' => Http::response([
                'prediction'      => 1,
                'risk_label'      => 'high_risk',
                'probability'     => 0.67,
                'model_version'   => 'lr_v1.0',
                'recommendations' => ['Improve sanitation access', 'Routine antenatal care'],
                'explanation'     => [
                    'method'      => 'SHAP',
                    'top_factors' => [[
                        'feature' => 'mother_height_cm',
                        'impact'  => 'increase_risk',
                        'value'   => 155,
                        'message' => 'Mother height contributes to increased risk.',
                    ]],
                ],
            ], 200),
        ]);
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => "Bearer {$this->issueToken($user)}",
            'Accept'        => 'application/json',
        ];
    }

    // ═══ PREDICTION ═══

    public function test_authenticated_user_can_predict(): void
    {
        $this->fakeMlSuccess();
        $user = $this->makeUser();

        $response = $this->postJson('/api/stunting/predict', $this->validPayload(), $this->authHeaders($user));

        $response->assertStatus(201)
                 ->assertJson(['success' => true, 'message' => 'Prediksi stunting berhasil.'])
                 ->assertJsonStructure(['data' => ['id', 'prediction', 'risk_label', 'probability', 'explanation', 'recommendations', 'model_version', 'latency_ms', 'status', 'created_at']]);

        $response->assertJsonPath('data.prediction', 1);
        $response->assertJsonPath('data.risk_label', 'high_risk');
        $response->assertJsonPath('data.model_version', 'lr_v1.0');
        $response->assertJsonPath('data.status', 'success');
    }

    public function test_prediction_persisted_to_database(): void
    {
        $this->fakeMlSuccess();
        $user = $this->makeUser();

        $this->postJson('/api/stunting/predict', $this->validPayload(), $this->authHeaders($user))->assertStatus(201);

        $this->assertDatabaseHas('stunting_predictions', [
            'user_id'       => $user->user_id,
            'prediction'    => 1,
            'risk_label'    => 'high_risk',
            'model_version' => 'lr_v1.0',
            'status'        => 'success',
        ]);
    }

    public function test_unauthenticated_user_cannot_predict(): void
    {
        $this->postJson('/api/stunting/predict', $this->validPayload())->assertStatus(401);
    }

    public function test_prediction_handles_ml_500(): void
    {
        Http::fake(['*/predict' => Http::response(['error' => 'Internal Server Error'], 500)]);
        $user = $this->makeUser();

        $response = $this->postJson('/api/stunting/predict', $this->validPayload(), $this->authHeaders($user));
        $response->assertJson(['success' => false]);
        $this->assertTrue(in_array($response->status(), [500, 502]));
    }

    // ═══ VALIDATION ═══

    public function test_validation_fails_empty_payload(): void
    {
        $user = $this->makeUser();
        $this->postJson('/api/stunting/predict', [], $this->authHeaders($user))
             ->assertStatus(422)
             ->assertJson(['success' => false, 'message' => 'Validasi gagal.']);
    }

    public function test_validation_fails_invalid_gender(): void
    {
        $user = $this->makeUser();
        $this->postJson('/api/stunting/predict', $this->validPayload(['child_gender' => 'invalid']), $this->authHeaders($user))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['child_gender']);
    }

    public function test_validation_fails_invalid_education(): void
    {
        $user = $this->makeUser();
        $this->postJson('/api/stunting/predict', $this->validPayload(['mother_education' => 'phd']), $this->authHeaders($user))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['mother_education']);
    }

    public function test_validation_fails_height_out_of_range(): void
    {
        $user = $this->makeUser();
        $this->postJson('/api/stunting/predict', $this->validPayload(['mother_height_cm' => 50]), $this->authHeaders($user))
             ->assertStatus(422)->assertJsonValidationErrors(['mother_height_cm']);
    }

    public function test_validation_fails_invalid_anc_place(): void
    {
        $user = $this->makeUser();
        $this->postJson('/api/stunting/predict', $this->validPayload(['anc_place' => 'home']), $this->authHeaders($user))
             ->assertStatus(422)->assertJsonValidationErrors(['anc_place']);
    }

    // ═══ QUESTIONS ═══

    public function test_questions_returns_metadata(): void
    {
        $this->getJson('/api/stunting/questions')
             ->assertOk()
             ->assertJson(['success' => true])
             ->assertJsonStructure(['data' => [['key', 'label', 'type', 'required']]]);
    }

    public function test_questions_returns_13_items(): void
    {
        $data = $this->getJson('/api/stunting/questions')->json('data');
        $this->assertCount(13, $data);
    }

    public function test_questions_accessible_without_auth(): void
    {
        $this->getJson('/api/stunting/questions')->assertOk();
    }
}
