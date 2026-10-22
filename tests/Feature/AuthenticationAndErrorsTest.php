<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

class AuthenticationAndErrorsTest extends ApiTestCase
{
    #[Test]
    public function health_needs_no_token(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('api_version', 'v1');
    }

    #[Test]
    public function a_missing_token_is_rejected(): void
    {
        $this->getJson('/api/v1/patients/23-0001')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Missing or invalid bearer token.', 'code' => 'UNAUTHENTICATED'])
            ->assertHeader('WWW-Authenticate', 'Bearer');
    }

    #[Test]
    public function a_wrong_token_is_rejected(): void
    {
        $this->json('GET', '/api/v1/patients/23-0001', [], $this->authHeaders('not-the-token'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function an_unset_server_token_fails_closed(): void
    {
        config(['chis.api_token' => '']);

        // Even an empty bearer token must not match an empty configured one.
        $this->json('GET', '/api/v1/patients/23-0001', [], ['Authorization' => 'Bearer ', 'Accept' => 'application/json'])
            ->assertStatus(500)
            ->assertJsonPath('code', 'SERVER_ERROR');
    }

    #[Test]
    public function unknown_routes_use_the_contract_error_shape(): void
    {
        $this->api('GET', 'nope')
            ->assertNotFound()
            ->assertExactJson(['message' => 'No such endpoint.', 'code' => 'NOT_FOUND']);
    }

    #[Test]
    public function unsupported_methods_use_the_contract_error_shape(): void
    {
        $this->api('DELETE', 'encounters')
            ->assertStatus(405)
            ->assertJsonPath('code', 'METHOD_NOT_ALLOWED');
    }

    #[Test]
    public function requests_over_the_limit_get_429_with_retry_after(): void
    {
        config(['chis.rate_limit_per_minute' => 2]);

        $this->api('GET', 'patients/23-0001')->assertOk();
        $this->api('GET', 'patients/23-0001')->assertOk();

        $this->api('GET', 'patients/23-0001')
            ->assertStatus(429)
            ->assertJsonPath('code', 'RATE_LIMITED')
            ->assertHeader('Retry-After');
    }
}
