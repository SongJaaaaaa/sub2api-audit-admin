<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_capacitor_origin_is_allowed_for_api_preflight(): void
    {
        config()->set('cors.allowed_origins', ['capacitor://localhost', 'https://localhost']);

        $response = $this->withHeaders([
            'Origin' => 'capacitor://localhost',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->options('/api/v1/auth/me');

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', 'capacitor://localhost');
        $response->assertHeader('Access-Control-Allow-Methods');
        $response->assertHeader('Access-Control-Allow-Headers');
    }

    public function test_unknown_origin_is_not_allowed(): void
    {
        config()->set('cors.allowed_origins', ['capacitor://localhost', 'https://localhost']);

        $response = $this->withHeaders([
            'Origin' => 'https://untrusted.example',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/auth/me');

        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }
}
