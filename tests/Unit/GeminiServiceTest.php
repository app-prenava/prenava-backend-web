<?php

namespace Tests\Unit;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    private GeminiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.api_key' => 'test-key-123']);
        config(['services.gemini.model' => 'gemini-2.0-flash']);
        config(['services.gemini.timeout' => 10]);
        $this->service = new GeminiService();
    }

    // ═══ COOKING GUIDE ═══

    public function test_cooking_guide_returns_structured_response(): void
    {
        Http::fake(['*generativelanguage*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'cooking_guide'  => 'Rebus bayam selama 3 menit...',
                    'nutrition_tips' => 'Bayam kaya zat besi...',
                    'meal_plan'      => 'Sarapan: bayam + telur...',
                ])]]],
            ]],
        ], 200)]);

        $result = $this->service->getCookingGuide(['Bayam', 'Telur']);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('cooking_guide', $result);
        $this->assertArrayHasKey('nutrition_tips', $result);
        $this->assertArrayHasKey('meal_plan', $result);
        $this->assertStringContainsString('bayam', strtolower($result['cooking_guide']));
    }

    public function test_cooking_guide_returns_null_without_api_key(): void
    {
        config(['services.gemini.api_key' => '']);
        $service = new GeminiService();

        $result = $service->getCookingGuide(['Bayam']);

        $this->assertNull($result);
    }

    public function test_cooking_guide_returns_null_on_api_error(): void
    {
        Http::fake(['*generativelanguage*' => Http::response(['error' => 'Unauthorized'], 401)]);

        $result = $this->service->getCookingGuide(['Bayam']);

        $this->assertNull($result);
    }

    // ═══ MEAL PLAN ═══

    public function test_meal_plan_returns_structured_response(): void
    {
        Http::fake(['*generativelanguage*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'cooking_guide'  => 'Panduan memasak...',
                    'nutrition_tips' => 'Protein penting...',
                    'meal_plan'      => 'Pagi: nasi + ikan...',
                ])]]],
            ]],
        ], 200)]);

        $result = $this->service->getMealPlan('high_risk', ['Tinggi badan ibu'], ['Ikan', 'Bayam']);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('meal_plan', $result);
    }

    // ═══ RESPONSE PARSING ═══

    public function test_handles_markdown_wrapped_json(): void
    {
        $jsonText = "```json\n" . json_encode([
            'cooking_guide'  => 'Guide',
            'nutrition_tips' => 'Tips',
            'meal_plan'      => 'Plan',
        ]) . "\n```";

        Http::fake(['*generativelanguage*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => $jsonText]]],
            ]],
        ], 200)]);

        $result = $this->service->getCookingGuide(['Bayam']);

        $this->assertNotNull($result);
        $this->assertEquals('Guide', $result['cooking_guide']);
    }

    public function test_handles_non_json_response_gracefully(): void
    {
        Http::fake(['*generativelanguage*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => 'This is plain text not JSON']]],
            ]],
        ], 200)]);

        $result = $this->service->getCookingGuide(['Bayam']);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('cooking_guide', $result);
        $this->assertStringContainsString('plain text', $result['cooking_guide']);
    }

    public function test_handles_empty_candidates(): void
    {
        Http::fake(['*generativelanguage*' => Http::response([
            'candidates' => [],
        ], 200)]);

        $result = $this->service->getCookingGuide(['Bayam']);

        $this->assertNull($result);
    }

    // ═══ PROMPT CONTENT ═══

    public function test_prompt_contains_food_names(): void
    {
        Http::fake(['*generativelanguage*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'cooking_guide' => 'Guide', 'nutrition_tips' => 'T', 'meal_plan' => 'P',
                ])]]],
            ]],
        ], 200)]);

        $this->service->getCookingGuide(['Tempe', 'Tahu', 'Kangkung']);

        Http::assertSent(function ($request) {
            $body = json_encode($request->data());
            return str_contains($body, 'Tempe') && str_contains($body, 'Tahu');
        });
    }
}
