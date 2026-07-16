<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sub2api.user_api.base_url', 'https://sub2api.test');
    }

    public function test_sub2api_admin_can_login_and_is_synced(): void
    {
        $this->fakeRemoteUser([
            'id' => 1,
            'name' => 'Song',
            'username' => 'song',
            'email' => 'Song@qq.com',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $res = $this->postJson('/api/v1/auth/login', [
            'account' => 'Song@qq.com',
            'password' => 'song123',
        ]);

        $res->assertOk()
            ->assertJsonPath('identity_type', 'admin')
            ->assertJsonPath('admin.sub2api_user_id', 1)
            ->assertJsonPath('admin.username', 'song')
            ->assertJsonPath('admin.email', 'song@qq.com')
            ->assertJsonStructure([
                'identity_type',
                'token',
                'admin' => ['id', 'sub2api_user_id', 'name', 'username', 'email', 'status'],
            ]);

        $this->assertDatabaseHas('admins', [
            'sub2api_user_id' => 1,
            'email' => 'song@qq.com',
            'password' => null,
        ]);
        Http::assertSentCount(1);
    }

    public function test_disabled_sub2api_admin_cannot_login(): void
    {
        $this->fakeRemoteUser([
            'id' => 2,
            'email' => 'disabled@example.com',
            'role' => 'admin',
            'status' => 'disabled',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'account' => 'disabled@example.com',
            'password' => 'secret123',
        ])->assertForbidden()
            ->assertJsonPath('message', '账号已被禁用');

        $this->assertDatabaseHas('admins', [
            'sub2api_user_id' => 2,
            'status' => Admin::STATUS_DISABLED,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_regular_sub2api_user_cannot_login_to_admin(): void
    {
        $this->fakeRemoteUser([
            'id' => 3,
            'email' => 'user@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'account' => 'user@example.com',
            'password' => 'secret123',
        ])->assertForbidden()
            ->assertJsonPath('message', '仅 Sub2API 管理员可登录');

        $this->assertDatabaseCount('admins', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_local_admin_password_is_not_a_login_source(): void
    {
        Admin::query()->create([
            'name' => '旧管理员',
            'email' => 'admin@example.com',
            'password' => 'admin123',
            'status' => Admin::STATUS_ACTIVE,
        ]);
        Http::fake([
            'https://sub2api.test/api/v1/auth/login' => Http::response(['message' => 'invalid'], 401),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'account' => 'admin@example.com',
            'password' => 'admin123',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_sub2api_bad_request_is_reported_as_invalid_credentials(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/auth/login' => Http::response(['message' => 'invalid'], 400),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'account' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', '账号或密码错误');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_guest_cannot_get_current_admin(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_admin_can_get_current_admin_and_logout(): void
    {
        $admin = $this->admin('admin@example.com');
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
        $admin = $this->admin('current@example.com', '当前管理员');
        $this->admin('other@example.com', '其他管理员');
        Admin::query()->create([
            'name' => '停用管理员',
            'email' => 'disabled@example.com',
            'status' => Admin::STATUS_DISABLED,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/auth/admin-options')
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonMissing(['email' => 'disabled@example.com'])
            ->assertJsonStructure(['items' => [['id', 'sub2api_user_id', 'name', 'email', 'status']]]);
    }

    private function admin(string $email, string $name = '管理员'): Admin
    {
        return Admin::query()->create([
            'name' => $name,
            'email' => $email,
            'status' => Admin::STATUS_ACTIVE,
        ]);
    }

    private function fakeRemoteUser(array $user): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/auth/login' => Http::response([
                'data' => [
                    'access_token' => 'temporary-upstream-token',
                    'user' => $user,
                ],
            ]),
        ]);
    }
}
