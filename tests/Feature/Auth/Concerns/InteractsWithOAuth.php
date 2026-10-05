<?php

namespace Tests\Feature\Auth\Concerns;

use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

trait InteractsWithOAuth
{
    /**
     * Build a fresh PKCE verifier/challenge pair (S256).
     *
     * @return array{verifier: string, challenge: string}
     */
    protected function pkcePair(): array
    {
        $verifier = $this->base64UrlEncode(random_bytes(32));
        $challenge = $this->base64UrlEncode(hash('sha256', $verifier, true));

        return [
            'verifier' => $verifier,
            'challenge' => $challenge,
        ];
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Create a public (secret-less, PKCE-only) first-party OAuth client.
     *
     * @param  string[]  $redirectUris
     */
    protected function makePublicClient(
        array $redirectUris = ['https://client.test/callback'],
        string $name = 'Test SPA'
    ): Client {
        return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            $name,
            $redirectUris,
            false
        );
    }

    /**
     * Build the /oauth/authorize URL with a PKCE challenge.
     */
    protected function authorizeUrl(
        Client $client,
        string $challenge,
        string $redirectUri = 'https://client.test/callback',
        string $state = 'test-state'
    ): string {
        return '/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Extract the authorization code from a redirect response.
     */
    protected function extractAuthorizationCode(TestResponse $response): string
    {
        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return (string) ($query['code'] ?? '');
    }

    /**
     * Exchange an authorization code for tokens using PKCE.
     */
    protected function exchangeCode(
        Client $client,
        string $code,
        string $verifier,
        string $redirectUri = 'https://client.test/callback'
    ): TestResponse {
        return $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'redirect_uri' => $redirectUri,
            'code' => $code,
            'code_verifier' => $verifier,
        ]);
    }
}
