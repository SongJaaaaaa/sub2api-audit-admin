<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_returns_ok_with_china_timezone(): void
    {
        $res = $this->getJson('/api/v1/health');

        $res->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('timezone', 'Asia/Shanghai')
            ->assertJsonStructure([
                'status',
                'timezone',
                'time',
            ]);
    }
}
