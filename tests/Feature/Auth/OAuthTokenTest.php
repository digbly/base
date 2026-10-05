<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Modules\Auth\Models\User;
use Tests\Feature\Auth\Concerns\InteractsWithOAuth;
use Tests\TestCase;

class OAuthTokenTest extends TestCase
{
    use InteractsWithOAuth, RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('passport:keys');
    }

    public function test_refresh_token_grant_returns_new_tokens(): void
    {
        $tokens = $this->issueTokensViaAuthorizationCode();

        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->client->id,
            'refresh_token' => $tokens['refresh_token'],
        ]);

        $response->assertOk()->assertJsonStructure([
            'token_type',
            'expires_in',
            'access_token',
            'refresh_token',
        ]);
        $this->assertNotSame($tokens['access_token'], $response->json('access_token'));
    }

    public function test_refresh_token_grant_rejects_invalid_token(): void
    {
        $client = $this->makePublicClient();

        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->id,
            'refresh_token' => 'not-a-valid-refresh-token',
        ]);

        $response->assertStatus(400);
    }

    public function test_password_grant_is_not_supported(): void
    {
        $client = $this->makePublicClient();

        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'username' => 'user@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(400)->assertJsonPath('error', 'unsupported_grant_type');
    }

    public function test_legacy_password_login_route_is_gone(): void
    {
        User::factory()->create([
            'email' => 'legacy-login@example.com',
            'password' => 'Password123!',
        ]);

        $this->postJson('/api/v1/auth/user/login', [
            'email' => 'legacy-login@example.com',
            'password' => 'Password123!',
        ])->assertStatus(404);
    }

    public function test_legacy_refresh_token_route_is_gone(): void
    {
        $this->postJson('/api/v1/auth/user/refresh-token', [
            'refresh_token' => 'whatever',
        ])->assertStatus(404);
    }

    /**
     * Run the full authorization-code + PKCE flow and return the token payload.
     *
     * @return array<string, mixed>
     */
    private function issueTokensViaAuthorizationCode(): array
    {
        $user = User::factory()->create();
        $this->client = $this->makePublicClient();
        ['verifier' => $verifier, 'challenge' => $challenge] = $this->pkcePair();

        $this->actingAs($user, 'web');

        $code = $this->extractAuthorizationCode(
            $this->get($this->authorizeUrl($this->client, $challenge))
        );

        return $this->exchangeCode($this->client, $code, $verifier)->json();
    }
}
