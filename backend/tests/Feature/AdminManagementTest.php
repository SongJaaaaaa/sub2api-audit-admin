<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_and_filter_accounts_with_full_summary(): void
    {
        $current = $this->admin('current@example.com', Admin::STATUS_ACTIVE);
        $this->admin('active@example.com', Admin::STATUS_ACTIVE, '运营管理员');
        $this->admin('disabled@example.com', Admin::STATUS_DISABLED, '停用管理员');

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
            ->assertJsonPath('items.0.email', 'active@example.com')
            ->assertJsonPath('summary.admin_count', 1)
            ->assertJsonPath('summary.active_count', 1);
    }

    public function test_admin_can_create_account_without_exposing_password(): void
    {
        $current = $this->admin('current@example.com', Admin::STATUS_ACTIVE);
        $res = $this->actingAs($current)->postJson('/api/v1/admins', [
            'name' => ' 新管理员 ',
            'email' => ' NEW@EXAMPLE.COM ',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
            'status' => Admin::STATUS_ACTIVE,
        ]);

        $res->assertCreated()
            ->assertJsonPath('message', '管理员账号已创建')
            ->assertJsonPath('admin.name', '新管理员')
            ->assertJsonPath('admin.email', 'new@example.com')
            ->assertJsonMissingPath('admin.password');

        $admin = Admin::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('newpass123', $admin->password));
        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $current->id,
            'action' => 'admin.create',
            'target_type' => 'admin',
            'target_id' => $admin->id,
        ]);
        $log = AuditLog::query()->where('action', 'admin.create')->firstOrFail();
        $this->assertArrayNotHasKey('password', $log->after_value);
        $this->assertStringNotContainsString('newpass123', json_encode($log->after_value));

        $this->postJson('/api/v1/auth/login', [
            'email' => 'new@example.com',
            'password' => 'newpass123',
        ])->assertOk()
            ->assertJsonPath('admin.id', $admin->id)
            ->assertJsonPath('admin.email', 'new@example.com')
            ->assertJsonStructure(['token']);
    }

    public function test_create_account_validates_unique_email_and_password_confirmation(): void
    {
        $current = $this->admin('current@example.com', Admin::STATUS_ACTIVE);

        $this->actingAs($current)->postJson('/api/v1/admins', [
            'name' => '重复账号',
            'email' => 'CURRENT@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
            'status' => 'unknown',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'status']);
    }

    private function admin(string $email, string $status, string $name = '管理员'): Admin
    {
        return Admin::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'secret123',
            'status' => $status,
        ]);
    }
}
