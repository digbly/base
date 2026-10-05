<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Tests\Feature\Auth\Concerns\InteractsWithOAuth;
use Tests\TestCase;

class OAuthAuthorizationCodeTest extends TestCase
{
    use InteractsWithOAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('passport:keys');
    }

    public function test_guest_is_redirected_to_login_from_authorize_endpoint(): void
    {
        $client = $this->makePublicClient();
        ['challenge' => $challenge] = $this->pkcePair();

        $response = $this->get($this->authorizeUrl($client, $challenge));

        $response->assertRedirect('/login');
        $this->assertStringContainsString(
            '/oauth/authorize',
            (string) session('url.intended')
        );
    }

    public function test_user_can_log_in_with_web_form(): void
    {
        $user = User::factory()->create([
            'password' => 'Password123!',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_web_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => 'Password123!',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_authorize_auto_approves_first_party_client_and_returns_code(): void
    {
        $user = User::factory()->create();
        $client = $this->makePublicClient();
        ['challenge' => $challenge] = $this->pkcePair();

        $this->actingAs($user, 'web');

        $response = $this->get($this->authorizeUrl($client, $challenge));

        $response->assertRedirect();
        $this->assertStringStartsWith(
            'https://client.test/callback',
            (string) $response->headers->get('Location')
        );
        $this->assertNotEmpty($this->extractAuthorizationCode($response));
        $this->assertStringContainsString('state=test-state', (string) $response->headers->get('Location'));
    }

    public function test_authorization_code_can_be_exchanged_for_tokens_with_pkce(): void
    {
        $user = User::factory()->create();
        $client = $this->makePublicClient();
        ['verifier' => $verifier, 'challenge' => $challenge] = $this->pkcePair();

        $this->actingAs($user, 'web');

        $code = $this->extractAuthorizationCode(
            $this->get($this->authorizeUrl($client, $challenge))
        );

        $response = $this->exchangeCode($client, $code, $verifier);

        $response->assertOk()->assertJsonStructure([
            'token_type',
            'expires_in',
            'access_token',
            'refresh_token',
        ]);
    }

    public function test_token_exchange_fails_with_wrong_code_verifier(): void
    {
        $user = User::factory()->create();
        $client = $this->makePublicClient();
        ['challenge' => $challenge] = $this->pkcePair();

        $this->actingAs($user, 'web');

        $code = $this->extractAuthorizationCode(
            $this->get($this->authorizeUrl($client, $challenge))
        );

        $response = $this->exchangeCode($client, $code, 'invalid-verifier-value');

        $response->assertStatus(400);
    }

    public function test_authorization_code_cannot_be_used_twice(): void
    {
        $user = User::factory()->create();
        $client = $this->makePublicClient();
        ['verifier' => $verifier, 'challenge' => $challenge] = $this->pkcePair();

        $this->actingAs($user, 'web');

        $code = $this->extractAuthorizationCode(
            $this->get($this->authorizeUrl($client, $challenge))
        );

        $this->exchangeCode($client, $code, $verifier)->assertOk();
        $this->exchangeCode($client, $code, $verifier)->assertStatus(400);
    }

    public function test_authorize_rejects_mismatched_redirect_uri(): void
    {
        $user = User::factory()->create();
        $client = $this->makePublicClient();
        ['challenge' => $challenge] = $this->pkcePair();

        $this->actingAs($user, 'web');

        $response = $this->get(
            $this->authorizeUrl($client, $challenge, 'https://evil.test/callback')
        );

        $response->assertStatus(401)->assertJsonPath('error', 'invalid_client');
    }
}
