<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\DailyTask;
use App\Models\Badge;
use App\Models\WaterIntake;
use App\Models\UserTaskLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;

class GamificationAndDailyTasksTest extends TestCase
{
    protected function getTestUser(): User
    {
        $uniq = uniqid();
        return User::create([
            'email'          => "test_bumil_{$uniq}@prenava.com",
            'name'           => 'Bunda Test',
            'password'       => bcrypt('password123'),
            'role'           => 'user',
            'category'       => 'ibu_hamil',
            'is_active'      => true,
            'total_points'   => 0,
            'points_balance' => 0,
        ]);
    }

    public function test_get_daily_progress_returns_maternal_tier_and_hydration()
    {
        $user = $this->getTestUser();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/user/daily-progress');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user_points' => ['current_balance', 'lifetime_points'],
                'tier_info' => ['tier', 'name', 'icon', 'color', 'next_tier', 'points_needed', 'streak_needed'],
                'streak',
                'longest_streak',
                'streak_details' => ['current_streak', 'longest_streak', 'is_streak_active_today'],
                'hydration' => ['current_glasses', 'target_glasses', 'current_ml', 'target_ml', 'is_completed_today'],
                'tasks',
                'unlocked_badges_count',
            ]);

        // Pastikan tugas TTD dan Piring Gizi ada dalam daftar untuk ibu hamil
        $tasks = collect($response->json('tasks'));
        $this->assertTrue($tasks->contains('task_type', 'supplement'), 'Tugas TTD harus muncul');
        $this->assertTrue($tasks->contains('task_type', 'food_compliance'), 'Tugas Piring Gizi harus muncul');
    }

    public function test_drink_water_increments_glasses_and_awards_points_at_8_glasses()
    {
        $user = $this->getTestUser();
        $token = JWTAuth::fromUser($user);

        // Reset water intake hari ini untuk pengujian bersih
        WaterIntake::where('user_id', $user->user_id)->delete();

        // Minum 1 gelas
        $res1 = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/user/hydration/drink');

        $res1->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'current_glasses' => 1,
                'current_ml' => 250,
                'is_target_reached' => false,
            ]);

        // Simulasikan minum hingga gelas ke-8
        for ($i = 2; $i <= 7; $i++) {
            $this->withHeader('Authorization', "Bearer $token")
                ->postJson('/api/user/hydration/drink');
        }

        $res8 = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/user/hydration/drink');

        $res8->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'current_glasses' => 8,
                'current_ml' => 2000,
                'is_target_reached' => true,
                'points_earned' => 10,
            ]);
    }

    public function test_get_badges_returns_catalog_with_progress()
    {
        $user = $this->getTestUser();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/user/badges');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'badges' => [
                    '*' => [
                        'id',
                        'badge_code',
                        'name',
                        'category',
                        'description',
                        'icon',
                        'bonus_points',
                        'is_unlocked',
                        'progress_current',
                        'progress_target',
                        'requirement_type',
                    ]
                ],
                'total_unlocked',
                'total_badges',
            ]);

        $this->assertGreaterThanOrEqual(7, $response->json('total_badges'));
    }
}
