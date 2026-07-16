<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Rebate\RebateUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Sub2ApiTestDatabase;
use Tests\TestCase;

class RebateAuthIsolationTest extends TestCase
{
    use RefreshDatabase;
    use Sub2ApiTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSub2ApiDatabase();
        config()->set('sub2api.user_api.base_url', 'https://sub2api.test');
        $this->insertSub2ApiUser();
        DB::connection('sub2api')->table('user_affiliates')->insert([
            'user_id' => 1001,
            'aff_code' => 'AFF1001',
            'inviter_id' => null,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownSub2ApiDatabase();

        parent::tearDown();
    }

    public function test_affiliate_login_proxies_sub2api_and_issues_a_local_token(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/auth/login' => Http::response([
                'data' => ['access_token' => 'temporary-upstream-token'],
            ]),
            'https://sub2api.test/api/v1/auth/me' => Http::response([
                'data' => ['user' => ['id' => 1001, 'role' => 'user', 'status' => 'active']],
            ]),
            'https://sub2api.test/api/v1/auth/logout' => Http::response(['code' => 0]),
        ]);

        $response = $this->postJson('/api/v1/affiliate/auth/login', [
            'account' => 'alpha@example.com',
            'password' => 'secret123',
        ])->assertOk()
            ->assertJsonPath('user.id', 1001)
            ->assertJsonPath('user.invite_code', 'AFF1001');

        $this->assertNotSame('', (string) $response->json('token'));
        $this->assertDatabaseHas('rebate_users', ['id' => 1001, 'email' => 'alpha@example.com']);
        $this->assertFalse(Schema::hasColumn('rebate_users', 'password'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sub2api.test/api/v1/auth/login'
            && $request['email'] === 'alpha@example.com'
            && $request['password'] === 'secret123'
            && ! $request->hasHeader('x-api-key'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sub2api.test/api/v1/auth/me'
            && $request->hasHeader('Authorization', 'Bearer temporary-upstream-token'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sub2api.test/api/v1/auth/logout');
    }

    public function test_unified_login_routes_regular_user_to_affiliate_identity(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/auth/login' => Http::response([
                'data' => ['access_token' => 'temporary-upstream-token'],
            ]),
            'https://sub2api.test/api/v1/auth/me' => Http::response([
                'data' => ['user' => ['id' => 1001, 'role' => 'user', 'status' => 'active']],
            ]),
            'https://sub2api.test/api/v1/auth/logout' => Http::response(['code' => 0]),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'account' => 'alpha@example.com',
            'password' => 'secret123',
        ])->assertOk()
            ->assertJsonPath('identity_type', 'affiliate')
            ->assertJsonPath('user.id', 1001)
            ->assertJsonMissingPath('admin');

        $token = (string) $response->json('token');
        $this->withToken($token)->getJson('/api/v1/affiliate/dashboard')->assertOk();
        $this->withToken($token)->getJson('/api/v1/dashboard')->assertForbidden();
    }

    public function test_admin_token_cannot_access_affiliate_api(): void
    {
        $admin = Admin::query()->create([
            'name' => '管理员',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('admin.id', $admin->id);
        $this->withToken($token)->getJson('/api/v1/affiliate/dashboard')->assertForbidden();
    }

    public function test_affiliate_token_cannot_access_any_admin_api(): void
    {
        $affiliate = RebateUser::query()->create([
            'id' => 1001,
            'email' => 'alpha@example.com',
            'status' => RebateUser::STATUS_ACTIVE,
            'aff_code' => 'AFF1001',
        ]);
        $token = $affiliate->createToken('affiliate-token')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/affiliate/auth/me')
            ->assertOk()->assertJsonPath('user.id', $affiliate->id);
        $this->withToken($token)->getJson('/api/v1/dashboard')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/sub2api/users')->assertForbidden();
    }
}
