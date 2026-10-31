<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Http\Controllers\AuthController;
use Tymon\JWTAuth\Facades\JWTAuth;
use ReflectionMethod;

class TokenLifespanTest extends TestCase
{
    public function test_auth_token_config_has_30_days_ttl_for_mobile_users()
    {
        $ttlMap = config('auth_tokens.ttl_seconds');

        $this->assertIsArray($ttlMap);
        $this->assertEquals(30 * 24 * 60 * 60, $ttlMap['ibu_hamil'], 'ibu_hamil must have 30 days TTL');
        $this->assertEquals(30 * 24 * 60 * 60, $ttlMap['umum'], 'umum must have 30 days TTL');
        $this->assertEquals(12 * 60 * 60, $ttlMap['bidan'], 'bidan must have 12 hours TTL');
        $this->assertEquals(30 * 24 * 60 * 60, $ttlMap['default'], 'default fallback must be 30 days TTL');
    }

    public function test_issue_token_generates_30_days_lifespan_for_ibu_hamil()
    {
        $user = new User([
            'name'          => 'Ibu Tes',
            'email'         => 'ibutes@prenava.com',
            'role'          => 'ibu_hamil',
            'token_version' => 1,
        ]);
        $user->user_id = 999;

        $controller = new AuthController();
        $ref = new ReflectionMethod($controller, 'issueToken');
        $ref->setAccessible(true);
        $token = $ref->invoke($controller, $user);

        $this->assertNotEmpty($token);

        $payload = JWTAuth::setToken($token)->getPayload();
        $iat = $payload->get('iat');
        $exp = $payload->get('exp');

        $this->assertNotNull($iat);
        $this->assertNotNull($exp);

        $diffSeconds = $exp - $iat;
        $diffDays = $diffSeconds / (24 * 3600);

        $this->assertEquals(30, $diffDays, 'Token for ibu_hamil must last exactly 30 days');
        $this->assertEquals(999, (int) $payload->get('uid'));
        $this->assertEquals('ibu_hamil', $payload->get('role'));
    }

    public function test_issue_token_generates_12_hours_lifespan_for_bidan()
    {
        $user = new User([
            'name'          => 'Bidan Tes',
            'email'         => 'bidantes@prenava.com',
            'role'          => 'bidan',
            'token_version' => 1,
        ]);
        $user->user_id = 888;

        $controller = new AuthController();
        $ref = new ReflectionMethod($controller, 'issueToken');
        $ref->setAccessible(true);
        $token = $ref->invoke($controller, $user);

        $payload = JWTAuth::setToken($token)->getPayload();
        $iat = $payload->get('iat');
        $exp = $payload->get('exp');

        $diffSeconds = $exp - $iat;
        $diffHours = $diffSeconds / 3600;

        $this->assertEquals(12, $diffHours, 'Token for bidan must last exactly 12 hours');
    }
}
