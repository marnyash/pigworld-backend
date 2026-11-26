<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiAvailabilityTest extends TestCase
{
    private string $marker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->marker = storage_path('framework/api-disabled');
        if (is_file($this->marker)) {
            unlink($this->marker);
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->marker)) {
            unlink($this->marker);
        }

        parent::tearDown();
    }

    public function test_api_stop_command_blocks_api_routes_until_started(): void
    {
        $this->artisan('api:availability', ['action' => 'stop'])
            ->expectsOutput('API stopped. All API routes now return HTTP 503.')
            ->assertExitCode(0);

        $this->postJson('/api/v1/auth/login', [])
            ->assertServiceUnavailable()
            ->assertJson(['message' => 'The API is temporarily unavailable.'])
            ->assertHeader('Retry-After', '60');

        $this->get('/up')->assertOk();

        $this->artisan('api:availability', ['action' => 'start'])
            ->expectsOutput('API started.')
            ->assertExitCode(0);

        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable();
    }
}
