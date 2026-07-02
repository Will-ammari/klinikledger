<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiErrorContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_use_standard_api_error_contract(): void
    {
        $response = $this->withHeader('X-Request-Id', 'test-request-id')
            ->postJson('/api/auth/login', []);

        $response->assertUnprocessable()
            ->assertHeader('X-Request-Id', 'test-request-id')
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.message', 'The given data was invalid.')
            ->assertJsonPath('error.request_id', 'test-request-id')
            ->assertJsonValidationErrors(['email', 'password'], 'error.details.errors');
    }

    public function test_unauthenticated_errors_use_standard_api_error_contract(): void
    {
        $response = $this->withHeader('X-Request-Id', 'auth-request-id')
            ->getJson('/api/me');

        $response->assertUnauthorized()
            ->assertHeader('X-Request-Id', 'auth-request-id')
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertJsonPath('error.request_id', 'auth-request-id');
    }
}
