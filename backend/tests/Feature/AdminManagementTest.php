<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_and_filter_synced_accounts(): void
    {
        $current = $this->admin(1, 'current@example.com', Admin::STATUS_ACTIVE);
        $this->admin(2, 'active@example.com', Admin::STATUS_ACTIVE, '运营管理员');
        $this->admin(3, 'disabled@example.com', Admin::STATUS_DISABLED, '停用管理员');

        $this->getJson('/api/v1/admins')->assertUnauthorized();
        $this->actingAs($current)
            ->getJson('/api/v1/admins?page=1&page_size=1')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('summary.admin_count', 3)
            ->assertJsonPath('summary.active_count', 2)
            ->assertJsonPath('summary.disabled_count', 1);

        $this->actingAs($current)
            ->getJson('/api/v1/admins?keyword=运营&status=active')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.sub2api_user_id', 2)
            ->assertJsonPath('items.0.email', 'active@example.com');
    }

    public function test_admin_accounts_are_read_only(): void
    {
        $current = $this->admin(1, 'current@example.com', Admin::STATUS_ACTIVE);

        $this->actingAs($current)->postJson('/api/v1/admins', [
            'name' => '新管理员',
            'email' => 'new@example.com',
            'password' => 'newpass123',
        ])->assertStatus(405);

        $this->assertDatabaseCount('admins', 1);
    }

    private function admin(int $sub2apiId, string $email, string $status, string $name = '管理员'): Admin
    {
        return Admin::query()->create([
            'sub2api_user_id' => $sub2apiId,
            'name' => $name,
            'email' => $email,
            'status' => $status,
        ]);
    }
}
