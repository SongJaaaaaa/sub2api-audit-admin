<?php

namespace Tests\Feature;

use App\Exceptions\Sub2ApiStatsException;
use App\Models\LedgerAdjustment;
use App\Models\Rebate\RebateConfig;
use App\Models\Rebate\RebateScanCursor;
use App\Services\Sub2Api\Sub2ApiAdminClient;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use App\Support\ChinaDateRange;
use App\Support\Sub2ApiNoteTag;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\Sub2ApiTestDatabase;
use Tests\TestCase;

class Sub2ApiClientTest extends TestCase
{
    use RefreshDatabase;
    use Sub2ApiTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSub2ApiDatabase();
        config()->set('sub2api.admin_api.base_url', 'https://sub2api.test');
        config()->set('sub2api.admin_api.key', 'test-key');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        $this->tearDownSub2ApiDatabase();

        parent::tearDown();
    }

    public function test_user_search_returns_only_public_fields(): void
    {
        $this->insertSub2ApiUser([
            'balance' => '12.34',
            'total_recharged' => '100.00',
        ]);
        $this->insertSub2ApiUser([
            'id' => 1002,
            'email' => 'beta@example.com',
            'username' => 'beta',
            'status' => 'disabled',
        ]);

        $repo = app(Sub2ApiReadRepository::class);
        $res = $repo->users(['keyword' => 'alpha'], 1, 20);
        $detail = $repo->user(1001);

        $this->assertSame(1, $res['total']);
        $this->assertSame(1, $res['summary']['user_count']);
        $this->assertSame(1, $res['summary']['active_count']);
        $this->assertSame(0, $res['summary']['disabled_count']);
        $this->assertSame('12.34', $res['summary']['balance_total']);
        $this->assertSame(0, $res['summary']['negative_balance_count']);
        $this->assertSame(0, $res['summary']['zero_balance_count']);
        $this->assertSame([
            'id',
            'email',
            'username',
            'role',
            'balance',
            'total_recharged',
            'status',
            'created_at',
            'updated_at',
            'last_used_at',
        ], array_keys($res['items'][0]));
        $this->assertSame(1001, $res['items'][0]['id']);
        $this->assertSame('alpha@example.com', $res['items'][0]['email']);
        $this->assertSame('12.34', $res['items'][0]['balance']);
        $this->assertSame('100', $res['items'][0]['total_recharged']);
        $this->assertSame('alpha@example.com', $detail['email']);
    }

    public function test_user_search_filters_by_latest_usage_time(): void
    {
        $this->insertSub2ApiUser(['id' => 1001]);
        $this->insertSub2ApiUser(['id' => 1002, 'email' => 'beta@example.com', 'username' => 'beta']);
        $this->insertSub2ApiUser(['id' => 1003, 'email' => 'unused@example.com', 'username' => 'unused']);
        DB::connection('sub2api')->table('usage_logs')->insert([
            ['user_id' => 1001, 'created_at' => '2026-07-13 15:59:59'],
            ['user_id' => 1001, 'created_at' => '2026-07-14 02:00:00'],
            ['user_id' => 1002, 'created_at' => '2026-07-13 15:00:00'],
        ]);

        $res = app(Sub2ApiReadRepository::class)->users([
            'last_used_start' => '2026-07-14',
            'last_used_end' => '2026-07-14',
        ], 1, 20);

        $this->assertSame(1, $res['total']);
        $this->assertSame(1001, $res['items'][0]['id']);
        $this->assertSame('2026-07-14 10:00:00', $res['items'][0]['last_used_at']);
    }

    public function test_lightweight_user_search_supports_email_username_and_id(): void
    {
        $this->insertSub2ApiUser();
        $this->insertSub2ApiUser([
            'id' => 1002,
            'email' => 'beta@example.com',
            'username' => 'second_user',
            'status' => 'disabled',
        ]);

        $repo = app(Sub2ApiReadRepository::class);

        $this->assertSame([1002], collect($repo->searchUsers('beta'))->pluck('id')->all());
        $this->assertSame([1002], collect($repo->searchUsers('second'))->pluck('id')->all());
        $this->assertSame([1001], collect($repo->searchUsers('1001'))->pluck('id')->all());
        $this->assertSame(
            ['id', 'email', 'username', 'status'],
            array_keys($repo->searchUsers('alpha')[0]),
        );
    }

    public function test_active_balance_snapshot_excludes_admin_disabled_and_deleted_users(): void
    {
        $this->insertSub2ApiUser(['id' => 1001, 'balance' => '12.5']);
        $this->insertSub2ApiUser(['id' => 1002, 'email' => 'beta@example.com', 'username' => 'beta', 'balance' => '7.5']);
        $this->insertSub2ApiUser(['id' => 1003, 'email' => 'admin@example.com', 'role' => 'admin', 'balance' => '100']);
        $this->insertSub2ApiUser(['id' => 1004, 'email' => 'disabled@example.com', 'status' => 'disabled', 'balance' => '100']);
        $this->insertSub2ApiUser(['id' => 1005, 'email' => 'deleted@example.com', 'balance' => '100', 'deleted_at' => '2026-07-01 00:00:00']);

        $res = app(Sub2ApiReadRepository::class)->activeUserBalanceSnapshot();

        $this->assertSame(2, $res['active_user_count']);
        $this->assertSame('20', $res['active_user_balance']);
        $this->assertArrayHasKey('as_of', $res);
    }

    public function test_remote_events_use_utc_half_open_boundaries(): void
    {
        $this->insertSub2ApiUser();
        DB::connection('sub2api')->table('redeem_codes')->insert([
            $this->redeem(1, '2026-07-01 15:59:59'),
            $this->redeem(2, '2026-07-01 16:00:00'),
            $this->redeem(3, '2026-07-02 15:59:59.999999'),
            $this->redeem(4, '2026-07-02 16:00:00'),
        ]);

        $start = CarbonImmutable::parse('2026-07-02 00:00:00', 'Asia/Shanghai')->utc();
        $end = CarbonImmutable::parse('2026-07-03 00:00:00', 'Asia/Shanghai')->utc();
        $events = app(Sub2ApiReadRepository::class)->adminAdjustmentEvents($start, $end);

        $this->assertSame([2, 3], collect($events)->pluck('remote_event_id')->all());
    }

    public function test_source_lookup_requires_user_and_complete_idempotency_key(): void
    {
        $exact = Sub2ApiNoteTag::make('ADJ-SAME', 'idem-1');
        $prefix = Sub2ApiNoteTag::make('ADJ-SAME', 'idem-10');
        $wrong = Sub2ApiNoteTag::make('ADJ-SAME', 'different-key');

        DB::connection('sub2api')->table('redeem_codes')->insert([
            $this->redeem(10, '2026-07-01 16:00:00', ['used_by' => 1001, 'notes' => $exact]),
            $this->redeem(11, '2026-07-01 16:01:00', ['used_by' => 1002, 'notes' => $exact]),
            $this->redeem(12, '2026-07-01 16:02:00', ['used_by' => 1001, 'notes' => $prefix]),
            $this->redeem(13, '2026-07-01 16:03:00', ['used_by' => 1001, 'notes' => $wrong]),
        ]);

        $rows = app(Sub2ApiReadRepository::class)->findAdminAdjustmentSources(1001, 'idem-1');

        $this->assertSame([10], collect($rows)->pluck('id')->all());
        $this->assertSame('idem-1', Sub2ApiNoteTag::idempotencyKey($rows[0]['notes']));
    }

    public function test_admin_client_sends_full_idempotency_key_for_balance_update(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response([
                'code' => 0,
                'data' => ['id' => 1001, 'balance' => '88.00'],
            ]),
        ]);

        app(Sub2ApiAdminClient::class)->updateUserBalance(
            1001,
            '10.00',
            'increment',
            Sub2ApiNoteTag::make('ADJ-1', 'idem-1001-full'),
            'idem-1001-full',
        );

        Http::assertSent(fn (Request $req): bool => $req->url() === 'https://sub2api.test/api/v1/admin/users/1001/balance'
            && $req->hasHeader('x-api-key', 'test-key')
            && $req->hasHeader('Idempotency-Key', 'idem-1001-full')
            && (float) $req['balance'] === 10.0
            && $req['operation'] === 'add');
    }

    public function test_affiliate_user_and_direct_children_come_from_sub2api_relationships(): void
    {
        $this->insertSub2ApiUser(['id' => 1001]);
        $this->insertSub2ApiUser(['id' => 1002, 'email' => 'beta@example.com', 'username' => 'beta']);
        $this->insertSub2ApiUser(['id' => 1003, 'email' => 'gamma@example.com', 'username' => 'gamma']);
        DB::connection('sub2api')->table('user_affiliates')->insert([
            ['user_id' => 1001, 'aff_code' => 'A1001', 'inviter_id' => null],
            ['user_id' => 1002, 'aff_code' => 'A1002', 'inviter_id' => 1001],
            ['user_id' => 1003, 'aff_code' => 'A1003', 'inviter_id' => 1002],
        ]);

        $repo = app(Sub2ApiReadRepository::class);

        $this->assertSame('A1002', $repo->affiliateUser(1002)['aff_code']);
        $this->assertSame(1001, $repo->affiliateUser(1002)['parent_user_id']);
        $this->assertSame([1002], collect($repo->affiliateChildren(1001)['items'])->pluck('id')->all());
    }

    public function test_rebate_event_sources_use_cursors_and_exclude_gifts_and_withdrawal_echo(): void
    {
        DB::connection('sub2api')->table('payment_orders')->insert([
            [
                'id' => 10,
                'user_id' => 1001,
                'amount' => '100.00',
                'out_trade_no' => 'PAY-10',
                'order_type' => 'balance',
                'status' => 'completed',
                'completed_at' => '2026-07-15 00:00:00',
                'created_at' => '2026-07-15 00:00:00',
            ],
        ]);
        DB::connection('sub2api')->table('redeem_codes')->insert([
            $this->redeem(20, '2026-07-15 00:01:00', ['type' => 'balance', 'value' => '30.00']),
            $this->redeem(30, '2026-07-15 00:02:00', ['value' => '24.00', 'notes' => Sub2ApiNoteTag::make('RBW1', 'rebate-withdrawal-1')]),
            $this->redeem(31, '2026-07-15 00:03:00', ['value' => '10.00', 'notes' => Sub2ApiNoteTag::make('GIFT1', 'gift-1')]),
            $this->redeem(32, '2026-07-15 00:04:00', ['value' => '10.00', 'notes' => Sub2ApiNoteTag::make('CASH1', 'cash-1')]),
        ]);
        foreach ([
            [30, 'RBW1', 'rebate-withdrawal-1', LedgerAdjustment::BUSINESS_REBATE_WITHDRAWAL, '0.00', '0.00'],
            [31, 'GIFT1', 'gift-1', null, '0.00', '10.00'],
            [32, 'CASH1', 'cash-1', null, '8.00', '2.00'],
        ] as [$sourceId, $ledgerNo, $key, $businessSource, $cash, $gift]) {
            LedgerAdjustment::query()->create([
                'ledger_no' => $ledgerNo,
                'idempotency_key' => $key,
                'business_source' => $businessSource,
                'business_id' => $businessSource ? '1' : null,
                'sub2api_user_id' => 1001,
                'sub2api_source_id' => $sourceId,
                'operation' => LedgerAdjustment::OP_INCREMENT,
                'amount' => '10.00',
                'cash_amount' => $cash,
                'gift_quota_amount' => $gift,
                'status' => LedgerAdjustment::STATUS_SUCCEEDED,
                'adjust_reason' => '测试',
            ]);
        }

        $repo = app(Sub2ApiReadRepository::class);
        $native = $repo->rebateEvents('native_recharge', null, 10);
        $redeem = $repo->rebateEvents('redeem', null, 10);
        $admin = $repo->rebateEvents('admin_adjustment', null, 10);

        $this->assertSame(['10'], collect($native['items'])->pluck('source_id')->all());
        $this->assertSame(['at' => '2026-07-15 00:00:00', 'id' => 10], json_decode($native['next_cursor'], true));
        $this->assertSame(['20'], collect($redeem['items'])->pluck('source_id')->all());
        $this->assertSame(['32'], collect($admin['items'])->pluck('source_id')->all());
        $this->assertSame('8.00', $admin['items'][0]['amount']);
        $this->assertSame(['at' => '2026-07-15 00:04:00', 'id' => 32], json_decode($admin['next_cursor'], true));
    }

    public function test_rebate_cursor_does_not_miss_a_lower_id_completed_later(): void
    {
        DB::connection('sub2api')->table('payment_orders')->insert([
            [
                'id' => 10,
                'user_id' => 1001,
                'amount' => '20.00',
                'out_trade_no' => 'PAY-10',
                'order_type' => 'balance',
                'status' => 'pending',
                'completed_at' => null,
                'created_at' => '2026-07-15 00:00:00',
            ],
            [
                'id' => 20,
                'user_id' => 1001,
                'amount' => '30.00',
                'out_trade_no' => 'PAY-20',
                'order_type' => 'balance',
                'status' => 'completed',
                'completed_at' => '2026-07-15 00:01:00',
                'created_at' => '2026-07-15 00:00:00',
            ],
        ]);
        $repo = app(Sub2ApiReadRepository::class);
        $first = $repo->rebateEvents('native_recharge', null, 10);

        DB::connection('sub2api')->table('payment_orders')->where('id', 10)->update([
            'status' => 'completed',
            'completed_at' => '2026-07-15 00:02:00',
        ]);
        $second = $repo->rebateEvents('native_recharge', $first['next_cursor'], 10);

        $this->assertSame(['20'], collect($first['items'])->pluck('source_id')->all());
        $this->assertSame(['10'], collect($second['items'])->pluck('source_id')->all());
    }

    public function test_cutover_command_locks_time_and_starts_each_cursor_at_the_time_boundary(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-15 08:00:00.987654', 'Asia/Shanghai'));
        $this->artisan('rebate:cutover')->assertSuccessful();

        $this->assertSame(
            '2026-07-15 08:00:00.000000',
            RebateConfig::query()->findOrFail(1)->rebate_cutover_at->format('Y-m-d H:i:s.u'),
        );
        $this->assertSame(3, RebateScanCursor::query()->count());
        foreach (RebateScanCursor::query()->pluck('cursor_value') as $cursor) {
            $this->assertSame([
                'at' => '2026-07-15 00:00:00',
                'id' => 0,
            ], json_decode($cursor, true));
        }

        DB::connection('sub2api')->table('payment_orders')->insert([
            'id' => 99,
            'user_id' => 1001,
            'amount' => '10.00',
            'out_trade_no' => 'PAY-SAME-SECOND',
            'order_type' => 'balance',
            'status' => 'completed',
            'completed_at' => '2026-07-15 00:00:00',
            'created_at' => '2026-07-15 00:00:00',
        ]);
        $cursor = RebateScanCursor::query()->where('source_type', 'native_recharge')->value('cursor_value');
        $events = app(Sub2ApiReadRepository::class)->rebateEvents('native_recharge', $cursor, 10);

        $this->assertSame(['99'], collect($events['items'])->pluck('source_id')->all());
    }

    public function test_stats_wrapper_requires_success_code(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/admin/dashboard/trend*' => Http::response([
                'code' => 1,
                'data' => ['trend' => []],
            ]),
        ]);

        $this->expectException(Sub2ApiStatsException::class);
        $this->expectExceptionMessage('Sub2API 官方统计响应结构异常');
        app(Sub2ApiAdminClient::class)->dashboardTrend(
            ChinaDateRange::make('2026-07-01', '2026-07-02'),
        );
    }

    public function test_stats_wrapper_rejects_rows_with_missing_fields(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/admin/dashboard/trend*' => Http::response([
                'code' => 0,
                'data' => [
                    'trend' => [[
                        'date' => '2026-07-01',
                        'requests' => 1,
                    ]],
                ],
            ]),
        ]);

        $this->expectException(Sub2ApiStatsException::class);
        $this->expectExceptionMessage('Sub2API 官方统计响应字段异常');
        app(Sub2ApiAdminClient::class)->dashboardTrend(
            ChinaDateRange::make('2026-07-01', '2026-07-02'),
        );
    }

    public function test_admin_client_users_and_balance_history_routes_keep_working(): void
    {
        $this->withoutMiddleware();
        Http::fake([
            'https://sub2api.test/api/v1/admin/users?page=1&page_size=20' => Http::response([
                'data' => [['id' => 1001, 'email' => 'alpha@example.com']],
            ]),
            'https://sub2api.test/api/v1/admin/users/1001' => Http::response([
                'data' => ['id' => 1001, 'email' => 'alpha@example.com', 'balance' => '45.00'],
            ]),
            'https://sub2api.test/api/v1/admin/users/1001/balance-history?page=1&page_size=8' => Http::response([
                'data' => [
                    'items' => [[
                        'id' => 88,
                        'type' => 'admin_balance',
                        'value' => -5,
                        'status' => 'used',
                        'used_at' => '2026-07-07T00:00:00+08:00',
                    ]],
                    'total' => 1,
                ],
            ]),
        ]);

        $users = app(Sub2ApiAdminClient::class)->users(1, 20);
        $this->assertSame(1001, $users['data'][0]['id']);

        $this->getJson('/api/v1/sub2api/users/1001/balance-history?page=1&page_size=8')
            ->assertOk()
            ->assertJsonPath('items.0.id', 88)
            ->assertJsonPath('items.0.operation', 'decrement')
            ->assertJsonPath('items.0.value', '-5.00')
            ->assertJsonPath('items.0.adjusted_account', 'alpha@example.com')
            ->assertJsonPath('items.0.before_balance', '50.00')
            ->assertJsonPath('items.0.after_balance', '45.00');
    }

    public function test_balance_history_does_not_reuse_source_linked_adjustment_by_legacy_key(): void
    {
        $this->withoutMiddleware();
        $adj = LedgerAdjustment::query()->create([
            'ledger_no' => 'ADJ-SOURCE-88',
            'idempotency_key' => 'same-key',
            'sub2api_user_id' => 1001,
            'sub2api_source_id' => 88,
            'operation' => LedgerAdjustment::OP_INCREMENT,
            'amount' => '5.00',
            'status' => LedgerAdjustment::STATUS_SUCCEEDED,
            'adjust_reason' => '测试关联',
            'confirmed_at' => '2026-07-07 08:00:00',
        ]);
        $notes = Sub2ApiNoteTag::make('REMOTE', 'same-key');

        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => Http::response([
                'data' => ['id' => 1001, 'email' => 'alpha@example.com', 'balance' => '50.00'],
            ]),
            'https://sub2api.test/api/v1/admin/users/1001/balance-history?page=1&page_size=20' => Http::response([
                'data' => [
                    'items' => [
                        [
                            'id' => 89,
                            'type' => 'admin_balance',
                            'value' => 2,
                            'status' => 'used',
                            'used_at' => '2026-07-07T09:00:00+08:00',
                            'notes' => $notes,
                        ],
                        [
                            'id' => 88,
                            'type' => 'admin_balance',
                            'value' => 5,
                            'status' => 'used',
                            'used_at' => '2026-07-07T08:00:00+08:00',
                            'notes' => $notes,
                        ],
                    ],
                    'total' => 2,
                ],
            ]),
        ]);

        $res = $this->getJson('/api/v1/sub2api/users/1001/balance-history')->assertOk();

        $res->assertJsonPath('items.0.id', 89)
            ->assertJsonPath('items.0.ledger_adjustment_id', null)
            ->assertJsonPath('items.1.id', 88)
            ->assertJsonPath('items.1.ledger_adjustment_id', $adj->id);
    }

    private function redeem(int $id, string $usedAt, array $values = []): array
    {
        return array_merge([
            'id' => $id,
            'type' => 'admin_balance',
            'value' => '10.00',
            'status' => 'used',
            'used_by' => 1001,
            'used_at' => $usedAt,
            'notes' => Sub2ApiNoteTag::make('ADJ-'.$id, 'idem-'.$id),
            'created_at' => $usedAt,
        ], $values);
    }
}
