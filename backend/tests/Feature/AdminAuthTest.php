<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_login(): void
    {
        $admin = Admin::query()->create([
            'name' => '管理员',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ]);

        $res->assertOk()
            ->assertJsonPath('admin.id', $admin->id)
            ->assertJsonPath('admin.email', $admin->email)
            ->assertJsonPath('admin.status', Admin::STATUS_ACTIVE)
            ->assertJsonStructure([
                'token',
                'admin' => ['id', 'name', 'email', 'status'],
            ]);
    }

    public function test_disabled_admin_cannot_login(): void
    {
        $admin = Admin::query()->create([
            'name' => '停用管理员',
            'email' => 'disabled@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_DISABLED,
        ]);

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ]);

        $res->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_guest_cannot_get_current_admin(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_admin_can_get_current_admin_and_logout(): void
    {
        $admin = Admin::query()->create([
            'name' => '管理员',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);

        $token = $admin->createToken('admin-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('admin.id', $admin->id);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', '已退出');

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
    public function test_admin_can_get_active_admin_options(): void
    {
        $admin = Admin::query()->create([
            'name' => '当前管理员',
            'email' => 'current@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);
        Admin::query()->create([
            'name' => '其他管理员',
            'email' => 'other@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);
        Admin::query()->create([
            'name' => '停用管理员',
            'email' => 'disabled@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_DISABLED,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/auth/admin-options')
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonMissing(['email' => 'disabled@example.com'])
            ->assertJsonStructure(['items' => [['id', 'name', 'email', 'status']]]);
    }

}
