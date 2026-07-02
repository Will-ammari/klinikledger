<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_component_statuses(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonStructure([
                'status',
                'checks' => [
                    'database' => ['status', 'latency_ms'],
                    'cache' => ['status', 'latency_ms'],
                    'queue' => ['status', 'latency_ms'],
                    'redis' => ['status', 'latency_ms'],
                ],
            ]);
    }
}
