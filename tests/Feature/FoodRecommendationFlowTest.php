<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Helpers\JwtTestHelpers;
use App\Models\User;
use App\Models\Food;
use App\Models\StuntingPrediction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class FoodRecommendationFlowTest extends TestCase
{
    use RefreshDatabase, JwtTestHelpers;

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => "Bearer {$this->issueToken($user)}",
            'Accept'        => 'application/json',
        ];
    }

    private function seedFoods(int $count = 10): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Food::create([
                'name'          => "Food {$i}",
                'protein'       => 20 - $i,
                'calories'      => 100 + ($i * 10),
                'carbohydrates' => 15 + $i,
                'fat'           => 5 + $i,
                'image_url'     => "https://example.com/food-{$i}.jpg",
            ]);
        }
    }

    private function createPrediction(User $user, array $overrides = []): StuntingPrediction
    {
        return StuntingPrediction::create(array_merge([
            'user_id'     => $user->user_id,
            'input_data'  => ['child_gender' => 'male'],
            'ml_payload'  => ['child_gender' => 1],
            'ml_response' => ['prediction' => 1, 'probability' => 0.67],
            'probability' => 0.67,
            'prediction'  => 1,
            'risk_label'  => 'high_risk',
            'explanation' => [
                'method'      => 'SHAP',
                'top_factors' => [
                    ['feature' => 'mother_height_cm', 'impact' => 'increase_risk', 'value' => 148],
                    ['feature' => 'improved_sanitation', 'impact' => 'increase_risk', 'value' => 0],
                ],
            ],
            'recommendations' => ['Improve nutrition'],
            'model_version'   => 'lr_v1.0',
            'latency_ms'      => 150,
            'status'          => 'success',
        ], $overrides));
    }

    // ═══ RECOMMENDATION ENDPOINT ═══

    public function test_recommendation_returns_foods_and_ai_support(): void
    {
        $this->seedFoods();
        $user = $this->makeUser();
        $pred = $this->createPrediction($user);

        // Mock Gemini
        Http::fake(['*generativelanguage*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'cooking_guide'  => 'Cara memasak sehat...',
                    'nutrition_tips' => 'Tips nutrisi...',
                    'meal_plan'      => 'Sarapan: telur...',
                ])]]],
            ]],
        ], 200)]);

        config(['services.gemini.api_key' => 'test-key']);

        $response = $this->getJson(
            "/api/stunting/recommendations/{$pred->id}",
            $this->authHeaders($user)
        );

        $response->assertOk()
                 ->assertJson(['success' => true, 'cached' => false])
                 ->assertJsonStructure([
                     'data' => [
                         'prediction_summary' => ['risk_label', 'probability'],
                         'recommended_foods',
                         'ai_support',
                     ],
                 ]);

        $this->assertNotEmpty($response->json('data.recommended_foods'));
    }

    public function test_recommendation_caches_results(): void
    {
        $this->seedFoods();
        $user = $this->makeUser();
        $pred = $this->createPrediction($user);

        Http::fake(['*generativelanguage*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'cooking_guide' => 'Guide', 'nutrition_tips' => 'Tips', 'meal_plan' => 'Plan',
                ])]]],
            ]],
        ], 200)]);

        config(['services.gemini.api_key' => 'test-key']);

        // First call: cache miss
        $first = $this->getJson("/api/stunting/recommendations/{$pred->id}", $this->authHeaders($user));
        $first->assertOk()->assertJsonPath('cached', false);

        // Second call: cache hit
        $second = $this->getJson("/api/stunting/recommendations/{$pred->id}", $this->authHeaders($user));
        $second->assertOk()->assertJsonPath('cached', true);
    }

    public function test_recommendation_requires_authentication(): void
    {
        $this->getJson('/api/stunting/recommendations/1')->assertStatus(401);
    }

    public function test_recommendation_enforces_ownership(): void
    {
        $this->seedFoods();
        $owner   = $this->makeUser(['email' => 'owner@test.com']);
        $intruder = $this->makeUser(['email' => 'intruder@test.com']);
        $pred    = $this->createPrediction($owner);

        $response = $this->getJson(
            "/api/stunting/recommendations/{$pred->id}",
            $this->authHeaders($intruder)
        );

        $response->assertStatus(404)
                 ->assertJson(['success' => false]);
    }

    public function test_recommendation_returns_404_for_nonexistent(): void
    {
        $user = $this->makeUser();

        $this->getJson('/api/stunting/recommendations/99999', $this->authHeaders($user))
             ->assertStatus(404);
    }

    public function test_low_risk_skips_gemini(): void
    {
        $this->seedFoods();
        $user = $this->makeUser();
        $pred = $this->createPrediction($user, [
            'prediction'  => 0,
            'risk_label'  => 'low_risk',
            'probability' => 0.25,
        ]);

        // No Http::fake — Gemini should NOT be called for low_risk
        $response = $this->getJson(
            "/api/stunting/recommendations/{$pred->id}",
            $this->authHeaders($user)
        );

        $response->assertOk()
                 ->assertJsonPath('data.ai_support', null)
                 ->assertJsonPath('data.prediction_summary.risk_label', 'low_risk');

        $this->assertNotEmpty($response->json('data.recommended_foods'));
    }

    public function test_food_card_response_structure(): void
    {
        $this->seedFoods();
        $user = $this->makeUser();
        $pred = $this->createPrediction($user, ['risk_label' => 'low_risk']);

        $response = $this->getJson(
            "/api/stunting/recommendations/{$pred->id}",
            $this->authHeaders($user)
        );

        $response->assertOk();
        $foods = $response->json('data.recommended_foods');

        $this->assertNotEmpty($foods);
        $firstFood = $foods[0];

        $this->assertArrayHasKey('id', $firstFood);
        $this->assertArrayHasKey('name', $firstFood);
        $this->assertArrayHasKey('protein', $firstFood);
        $this->assertArrayHasKey('calories', $firstFood);
        $this->assertArrayHasKey('image_url', $firstFood);
        $this->assertArrayHasKey('reason', $firstFood);
    }

    // ═══ FOOD CATALOG ENDPOINTS ═══

    public function test_foods_index_returns_paginated_list(): void
    {
        $this->seedFoods(25);

        $response = $this->getJson('/api/stunting/foods?per_page=10');

        $response->assertOk()
                 ->assertJson(['success' => true])
                 ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(25, $response->json('meta.total'));
    }

    public function test_foods_search_filters(): void
    {
        Food::create(['name' => 'Bayam Segar', 'protein' => 3.5, 'calories' => 36, 'carbohydrates' => 6.5, 'fat' => 0.5]);
        Food::create(['name' => 'Telur Ayam', 'protein' => 12.6, 'calories' => 154, 'carbohydrates' => 0.7, 'fat' => 11.3]);

        $response = $this->getJson('/api/stunting/foods?search=Bayam');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Bayam Segar', $response->json('data.0.name'));
    }

    public function test_foods_show_returns_single_food(): void
    {
        $food = Food::create(['name' => 'Ikan Gabus', 'protein' => 25.2, 'calories' => 74, 'carbohydrates' => 0, 'fat' => 1.7]);

        $this->getJson("/api/stunting/foods/{$food->id}")
             ->assertOk()
             ->assertJsonPath('data.name', 'Ikan Gabus')
             ->assertJsonPath('data.protein', 25.2);
    }

    public function test_foods_show_returns_404(): void
    {
        $this->getJson('/api/stunting/foods/99999')
             ->assertStatus(404)
             ->assertJson(['success' => false]);
    }

    public function test_foods_accessible_without_auth(): void
    {
        $this->seedFoods(3);
        $this->getJson('/api/stunting/foods')->assertOk();
    }

    // ═══ NO GEMINI DURING PREDICTION ═══

    public function test_prediction_does_not_call_gemini(): void
    {
        Http::fake([
            '*/predict'              => Http::response([
                'prediction'    => 1,
                'risk_label'    => 'high_risk',
                'probability'   => 0.67,
                'model_version' => 'lr_v1.0',
                'explanation'   => ['method' => 'SHAP', 'top_factors' => []],
                'recommendations' => ['Routine care'],
            ], 200),
            '*generativelanguage*' => Http::response([], 200),
        ]);

        $user = $this->makeUser();

        $this->postJson('/api/stunting/predict', [
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
        ], $this->authHeaders($user))->assertStatus(201);

        // Gemini should NOT have been called
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generativelanguage'));
    }
}
