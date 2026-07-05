<?php

namespace Tests\Feature;

use App\Services\Sub2Api\Sub2ApiAdminClient;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Sub2ApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.sub2api', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sub2api');

        $this->createSub2ApiTables();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sub2api');

        parent::tearDown();
    }

    public function test_user_search_returns_public_user_fields(): void
    {
        DB::connection('sub2api')->table('users')->insert([
            [
                'id' => 1001,
                'email' => 'alpha@example.com',
                'username' => 'alpha',
                'role' => 'user',
                'balance' => '12.34',
                'total_recharged' => '100.00',
                'status' => 'active',
                'created_at' => '2026-07-01 00:00:00',
                'updated_at' => '2026-07-02 00:00:00',
                'deleted_at' => null,
            ],
            [
                'id' => 1002,
                'email' => 'beta@example.com',
                'username' => 'beta',
                'role' => 'user',
                'balance' => '0.00',
                'total_recharged' => '0.00',
                'status' => 'disabled',
                'created_at' => '2026-07-01 00:00:00',
                'updated_at' => '2026-07-02 00:00:00',
                'deleted_at' => null,
            ],
        ]);

        $repo = app(Sub2ApiReadRepository::class);
        $res = $repo->users(['keyword' => 'alpha'], 1, 20);

        $this->assertSame(1, $res['total']);
        $this->assertSame(1001, $res['items'][0]['id']);
        $this->assertSame('alpha@example.com', $res['items'][0]['email']);
        $this->assertSame('alpha', $res['items'][0]['username']);
        $this->assertSame('12.34', $res['items'][0]['balance']);
        $this->assertSame('100.00', $res['items'][0]['total_recharged']);
        $this->assertSame('active', $res['items'][0]['status']);
        $this->assertArrayNotHasKey('password', $res['items'][0]);

        $detail = $repo->user(1001);
        $this->assertSame('alpha@example.com', $detail['email']);
    }

    public function test_usage_stats_group_by_model(): void
    {
        DB::connection('sub2api')->table('usage_logs')->insert([
            [
                'id' => 1,
                'user_id' => 1001,
                'model' => 'gpt-5.5',
                'total_cost' => '2.50',
                'actual_cost' => '2.00',
                'created_at' => '2026-07-05 01:00:00',
            ],
            [
                'id' => 2,
                'user_id' => 1002,
                'model' => 'gpt-5.5',
                'total_cost' => '3.50',
                'actual_cost' => '3.00',
                'created_at' => '2026-07-05 02:00:00',
            ],
            [
                'id' => 3,
                'user_id' => 1001,
                'model' => 'claude-opus-4-8',
                'total_cost' => '1.00',
                'actual_cost' => '0.80',
                'created_at' => '2026-07-05 03:00:00',
            ],
        ]);

        $repo = app(Sub2ApiReadRepository::class);
        $from = CarbonImmutable::parse('2026-07-05 00:00:00');
        $to = CarbonImmutable::parse('2026-07-06 00:00:00');

        $summary = $repo->usageSummary($from, $to, []);
        $ranking = $repo->modelRanking($from, $to, [], 10);

        $this->assertSame(3, $summary['request_count']);
        $this->assertSame(2, $summary['user_count']);
        $this->assertSame(2, $summary['model_count']);
        $this->assertSame('7', $summary['total_cost']);
        $this->assertSame('5.8', $summary['actual_cost']);
        $this->assertSame('gpt-5.5', $ranking[0]['model']);
        $this->assertSame(2, $ranking[0]['request_count']);
        $this->assertSame(2, $ranking[0]['user_count']);
        $this->assertSame('6', $ranking[0]['total_cost']);
    }

    public function test_recharge_source_counts_are_readable(): void
    {
        DB::connection('sub2api')->table('payment_orders')->insert([
            [
                'id' => 1,
                'user_id' => 1001,
                'amount' => '20.00',
                'order_type' => 'balance',
                'status' => 'COMPLETED',
                'completed_at' => '2026-07-05 01:00:00',
                'created_at' => '2026-07-05 00:50:00',
            ],
        ]);
        DB::connection('sub2api')->table('redeem_codes')->insert([
            [
                'id' => 10,
                'type' => 'admin_balance',
                'value' => '50.00',
                'status' => 'used',
                'used_by' => 1001,
                'used_at' => '2026-07-05 02:00:00',
                'notes' => '充值',
                'created_at' => '2026-07-05 01:50:00',
            ],
            [
                'id' => 11,
                'type' => 'balance',
                'value' => '10.00',
                'status' => 'used',
                'used_by' => 1002,
                'used_at' => '2026-07-05 03:00:00',
                'notes' => '兑换',
                'created_at' => '2026-07-05 02:50:00',
            ],
        ]);

        $summary = app(Sub2ApiReadRepository::class)->rechargeSourceSummary();

        $this->assertSame(1, $summary['payment_orders_completed']);
        $this->assertSame([
            ['type' => 'admin_balance', 'count' => 1],
            ['type' => 'balance', 'count' => 1],
        ], $summary['redeem_codes_used']);
    }

    public function test_admin_client_calls_users_api_without_leaking_key(): void
    {
        config()->set('sub2api.admin_api.base_url', 'https://sub2api.test');
        config()->set('sub2api.admin_api.key', 'secret-key');

        Http::fake([
            'https://sub2api.test/api/v1/admin/users?page=1&page_size=20' => Http::response([
                'data' => [
                    ['id' => 1001, 'email' => 'alpha@example.com'],
                ],
            ]),
        ]);

        $res = app(Sub2ApiAdminClient::class)->users(1, 20);

        $this->assertSame(1001, $res['data'][0]['id']);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://sub2api.test/api/v1/admin/users?page=1&page_size=20'
            && $request->hasHeader('x-api-key', 'secret-key'));
    }

    public function test_admin_client_sends_idempotency_key_for_balance_update(): void
    {
        config()->set('sub2api.admin_api.base_url', 'https://sub2api.test');
        config()->set('sub2api.admin_api.key', 'secret-key');

        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response([
                'data' => ['id' => 1001, 'balance' => '88.00'],
            ]),
        ]);

        app(Sub2ApiAdminClient::class)->updateUserBalance(
            1001,
            '10.00',
            'increment',
            'ledger_no=ADJ202607050001',
            'idem-1001',
        );

        Http::assertSent(fn ($request): bool => $request->url() === 'https://sub2api.test/api/v1/admin/users/1001/balance'
            && $request->hasHeader('Idempotency-Key', 'idem-1001')
            && $request['balance'] === '10.00'
            && $request['operation'] === 'increment');
    }

    public function test_sub2api_routes_return_real_repository_data(): void
    {
        $this->withoutMiddleware();

        DB::connection('sub2api')->table('users')->insert([
            'id' => 1001,
            'email' => 'alpha@example.com',
            'username' => 'alpha',
            'role' => 'user',
            'balance' => '12.34',
            'total_recharged' => '100.00',
            'status' => 'active',
            'created_at' => '2026-07-01 00:00:00',
            'updated_at' => '2026-07-02 00:00:00',
            'deleted_at' => null,
        ]);
        DB::connection('sub2api')->table('usage_logs')->insert([
            'id' => 1,
            'user_id' => 1001,
            'model' => 'gpt-5.5',
            'total_cost' => '2.50',
            'actual_cost' => '2.00',
            'created_at' => '2026-07-05 01:00:00',
        ]);

        $this->getJson('/api/v1/sub2api/users?keyword=alpha')
            ->assertOk()
            ->assertJsonPath('items.0.email', 'alpha@example.com');

        $this->getJson('/api/v1/sub2api/model-stats?from=2026-07-05 00:00:00&to=2026-07-06 00:00:00')
            ->assertOk()
            ->assertJsonPath('summary.request_count', 1)
            ->assertJsonPath('models.0.model', 'gpt-5.5');
    }

    private function createSub2ApiTables(): void
    {
        Schema::connection('sub2api')->create('users', function ($table): void {
            $table->integer('id')->primary();
            $table->string('email');
            $table->string('username')->nullable();
            $table->string('role')->nullable();
            $table->decimal('balance', 16, 2)->default(0);
            $table->decimal('total_recharged', 16, 2)->default(0);
            $table->string('status')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::connection('sub2api')->create('usage_logs', function ($table): void {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->string('model');
            $table->decimal('total_cost', 18, 6)->default(0);
            $table->decimal('actual_cost', 18, 6)->default(0);
            $table->timestamp('created_at');
        });

        Schema::connection('sub2api')->create('payment_orders', function ($table): void {
            $table->integer('id')->primary();
            $table->integer('user_id')->nullable();
            $table->decimal('amount', 16, 2)->default(0);
            $table->string('order_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection('sub2api')->create('redeem_codes', function ($table): void {
            $table->integer('id')->primary();
            $table->string('type')->nullable();
            $table->decimal('value', 16, 2)->default(0);
            $table->string('status')->nullable();
            $table->integer('used_by')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
