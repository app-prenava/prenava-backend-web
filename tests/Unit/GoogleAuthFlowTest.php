<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Http;
use Tymon\JWTAuth\Facades\JWTAuth;
use ReflectionMethod;

class GoogleAuthFlowTest extends TestCase
{
    public function test_google_services_config_contains_client_ids()
    {
        $webId = config('services.google.web_client_id');
        $androidId = config('services.google.android_client_id');
        $allowed = config('services.google.allowed_client_ids');

        $this->assertNotEmpty($webId, 'Web Client ID must not be empty');
        $this->assertNotEmpty($androidId, 'Android Client ID must not be empty');
        $this->assertIsArray($allowed);
        $this->assertContains($webId, $allowed);
        $this->assertContains($androidId, $allowed);
    }

    public function test_verify_google_token_accepts_valid_audience()
    {
        $webId = config('services.google.web_client_id');

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub'            => '10987654321',
                'email'          => 'ibu.resmi@prenava.com',
                'name'           => 'Ibu Hamil Prenava',
                'picture'        => 'https://lh3.googleusercontent.com/a/photo_sample',
                'email_verified' => 'true',
                'aud'            => $webId,
            ], 200),
        ]);

        $controller = new GoogleAuthController();
        $ref = new ReflectionMethod($controller, 'verifyGoogleToken');
        $ref->setAccessible(true);
        $result = $ref->invoke($controller, 'mock-valid-id-token');

        $this->assertNotNull($result);
        $this->assertEquals('10987654321', $result['sub']);
        $this->assertEquals('ibu.resmi@prenava.com', $result['email']);
        $this->assertEquals('Ibu Hamil Prenava', $result['name']);
        $this->assertEquals('https://lh3.googleusercontent.com/a/photo_sample', $result['picture']);
    }

    public function test_verify_google_token_rejects_unauthorized_audience()
    {
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub'            => '10987654321',
                'email'          => 'malicious@example.com',
                'email_verified' => 'true',
                'aud'            => 'unauthorized-client-id.apps.googleusercontent.com',
            ], 200),
        ]);

        $controller = new GoogleAuthController();
        $ref = new ReflectionMethod($controller, 'verifyGoogleToken');
        $ref->setAccessible(true);
        $result = $ref->invoke($controller, 'mock-unauthorized-id-token');

        $this->assertNull($result, 'Must return null for untrusted audience');
    }

    public function test_issue_token_generates_30_days_jwt_for_google_login()
    {
        $user = new User([
            'name'          => 'Ibu Google Test',
            'email'         => 'ibugoogle@prenava.com',
            'role'          => 'ibu_hamil',
            'token_version' => 1,
        ]);
        $user->user_id = 888;

        $controller = new GoogleAuthController();
        $ref = new ReflectionMethod($controller, 'issueToken');
        $ref->setAccessible(true);
        $token = $ref->invoke($controller, $user);

        $this->assertNotEmpty($token);

        $payload = JWTAuth::setToken($token)->getPayload();
        $iat = $payload->get('iat');
        $exp = $payload->get('exp');

        $this->assertNotNull($iat);
        $this->assertNotNull($exp);

        $diffDays = ($exp - $iat) / (24 * 3600);
        $this->assertEquals(30, round($diffDays), 'Google login JWT must expire in 30 days');
    }
}
