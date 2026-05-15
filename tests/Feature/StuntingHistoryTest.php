<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Helpers\JwtTestHelpers;
use App\Models\User;
use App\Models\StuntingPrediction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StuntingHistoryTest extends TestCase
{
    use RefreshDatabase, JwtTestHelpers;

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => "Bearer {$this->issueToken($user)}",
            'Accept'        => 'application/json',
        ];
    }

    private function createPrediction(User $user, array $overrides = []): StuntingPrediction
    {
        return StuntingPrediction::create(array_merge([
            'user_id'         => $user->user_id,
            'input_data'      => ['child_gender' => 'male'],
            'ml_payload'      => ['child_gender' => 1],
            'ml_response'     => ['prediction' => 1, 'probability' => 0.67],
            'probability'     => 0.67,
            'prediction'      => 1,
            'risk_label'      => 'high_risk',
            'explanation'     => ['method' => 'SHAP', 'top_factors' => []],
            'recommendations' => ['Routine antenatal care'],
            'model_version'   => 'lr_v1.0',
            'latency_ms'      => 150,
            'status'          => 'success',
        ], $overrides));
    }

    // ═══ HISTORY LIST ═══

    public function test_user_sees_own_history(): void
    {
        $user = $this->makeUser();
        $this->createPrediction($user);
        $this->createPrediction($user);

        $response = $this->getJson('/api/stunting/history', $this->authHeaders($user));

        $response->assertOk()
                 ->assertJson(['success' => true])
                 ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_cannot_see_other_users_history(): void
    {
        $user1 = $this->makeUser(['email' => 'user1@test.com']);
        $user2 = $this->makeUser(['email' => 'user2@test.com']);

        $this->createPrediction($user1);
        $this->createPrediction($user2);

        $response = $this->getJson('/api/stunting/history', $this->authHeaders($user1));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_history_is_sorted_latest_first(): void
    {
        $user = $this->makeUser();

        $old = $this->createPrediction($user, ['created_at' => now()->subHour()]);
        $new = $this->createPrediction($user, ['created_at' => now()]);

        $response = $this->getJson('/api/stunting/history', $this->authHeaders($user));
        $data = $response->json('data');

        $this->assertEquals($new->id, $data[0]['id']);
        $this->assertEquals($old->id, $data[1]['id']);
    }

    public function test_history_pagination_works(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 15; $i++) {
            $this->createPrediction($user);
        }

        $response = $this->getJson('/api/stunting/history?per_page=5', $this->authHeaders($user));

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(3, $response->json('meta.last_page'));
        $this->assertEquals(15, $response->json('meta.total'));
    }

    public function test_history_excludes_failed_predictions(): void
    {
        $user = $this->makeUser();

        $this->createPrediction($user, ['status' => 'success']);
        $this->createPrediction($user, ['status' => 'failed']);
        $this->createPrediction($user, ['status' => 'timeout']);

        $response = $this->getJson('/api/stunting/history', $this->authHeaders($user));

        $this->assertCount(1, $response->json('data'));
    }

    public function test_history_requires_authentication(): void
    {
        $this->getJson('/api/stunting/history')->assertStatus(401);
    }

    // ═══ HISTORY DETAIL ═══

    public function test_user_can_view_own_prediction_detail(): void
    {
        $user = $this->makeUser();
        $prediction = $this->createPrediction($user);

        $response = $this->getJson("/api/stunting/history/{$prediction->id}", $this->authHeaders($user));

        $response->assertOk()
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.id', $prediction->id)
                 ->assertJsonPath('data.risk_label', 'high_risk')
                 ->assertJsonPath('data.model_version', 'lr_v1.0');
    }

    public function test_user_cannot_view_other_users_prediction(): void
    {
        $user1 = $this->makeUser(['email' => 'owner@test.com']);
        $user2 = $this->makeUser(['email' => 'intruder@test.com']);

        $prediction = $this->createPrediction($user1);

        $response = $this->getJson("/api/stunting/history/{$prediction->id}", $this->authHeaders($user2));

        $response->assertStatus(404)
                 ->assertJson(['success' => false]);
    }

    public function test_nonexistent_prediction_returns_404(): void
    {
        $user = $this->makeUser();

        $response = $this->getJson('/api/stunting/history/99999', $this->authHeaders($user));

        $response->assertStatus(404)
                 ->assertJson(['success' => false, 'message' => 'Data prediksi tidak ditemukan.']);
    }

    public function test_detail_requires_authentication(): void
    {
        $this->getJson('/api/stunting/history/1')->assertStatus(401);
    }
}
