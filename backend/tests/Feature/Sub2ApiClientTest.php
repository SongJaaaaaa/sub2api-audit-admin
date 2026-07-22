<?php

namespace Tests\Feature;

use App\Exceptions\Sub2ApiStatsException;
use App\Models\LedgerAdjustment;
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
        $this->tearDownSub2ApiDatabase();

        parent::tearDown();
    }

    public function test_user_search_returns_only_public_fields(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => Http::response([
                'code' => 0,
                'data' => [
                    'id' => 1001,
                    'email' => 'alpha@example.com',
                    'username' => 'alpha',
                    'role' => 'user',
                    'balance' => '12.34',
                    'total_recharged' => '100.00',
                    'status' => 'active',
                    'created_at' => '2026-07-01 00:00:00',
                    'updated_at' => '2026-07-01 00:00:00',
                ],
            ]),
        ]);
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

    public function test_admin_client_uses_official_user_pagination_and_filters(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/admin/users*' => Http::response([
                'code' => 0,
                'data' => [
                    'items' => [['id' => 1001]],
                    'total' => 6974,
                    'page' => 2,
                    'page_size' => 20,
                    'pages' => 349,
                ],
            ]),
        ]);

        $res = (new Sub2ApiAdminClient())->users(2, 20, [
            'search' => 'alpha@example.com',
            'status' => 'disabled',
            'include_subscriptions' => false,
            'sort_by' => 'balance',
            'sort_order' => 'asc',
        ]);

        $this->assertSame(6974, $res['data']['total']);
        Http::assertSent(function (Request $req): bool {
            parse_str((string) parse_url($req->url(), PHP_URL_QUERY), $query);

            return $query === [
                'page' => '2',
                'page_size' => '20',
                'search' => 'alpha@example.com',
                'status' => 'disabled',
                'include_subscriptions' => '0',
                'sort_by' => 'balance',
                'sort_order' => 'asc',
            ];
        });
    }

    public function test_users_can_filter_by_exact_id_and_emails(): void
    {
        $this->insertSub2ApiUser(['id' => 1001, 'email' => 'alpha@example.com']);
        $this->insertSub2ApiUser(['id' => 1002, 'email' => 'beta@example.com']);
        $this->insertSub2ApiUser(['id' => 1003, 'email' => 'gamma@example.com']);
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1002' => Http::response([
                'code' => 0,
                'data' => [
                    'id' => 1002,
                    'email' => 'beta@example.com',
                    'username' => 'alpha',
                    'role' => 'user',
                    'balance' => '0',
                    'total_recharged' => '0',
                    'status' => 'active',
                ],
            ]),
        ]);
        $this->withoutMiddleware();

        $this->getJson('/api/v1/sub2api/users?user_id=1002')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.email', 'beta@example.com');

        $this->getJson('/api/v1/sub2api/users?emails[]=alpha%40example.com&emails[]=gamma%40example.com')
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.email', 'alpha@example.com')
            ->assertJsonPath('items.1.email', 'gamma@example.com');
    }

    public function test_users_can_sort_balance_on_the_server(): void
    {
        $this->insertSub2ApiUser(['id' => 1001, 'balance' => '20.00']);
        $this->insertSub2ApiUser(['id' => 1002, 'email' => 'beta@example.com', 'balance' => '-5.00']);
        $this->insertSub2ApiUser(['id' => 1003, 'email' => 'gamma@example.com', 'balance' => '10.00']);
        $this->withoutMiddleware();

        $this->getJson('/api/v1/sub2api/users?sort_by=balance&sort_order=asc&page_size=2')
            ->assertOk()
            ->assertJsonPath('items.0.id', 1002)
            ->assertJsonPath('items.1.id', 1003)
            ->assertJsonPath('summary.user_count', 3);
        $this->getJson('/api/v1/sub2api/users?sort_by=balance&sort_order=desc&page=2&page_size=2')
            ->assertOk()
            ->assertJsonPath('items.0.id', 1002);
        $this->getJson('/api/v1/sub2api/users?page_size=1')
            ->assertOk()
            ->assertJsonPath('items.0.id', 1003);
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
            && ! is_string($req['balance'])
            && (float) $req['balance'] === 10.0
            && $req['operation'] === 'add');
    }

    public function test_stats_wrapper_requires_success_code(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/admin/dashboard/models*' => Http::response([
                'code' => 1,
                'data' => ['models' => []],
            ]),
        ]);

        $this->expectException(Sub2ApiStatsException::class);
        $this->expectExceptionMessage('Sub2API 官方统计响应结构异常');
        app(Sub2ApiAdminClient::class)->dashboardModels(
            ChinaDateRange::make('2026-07-01', '2026-07-02'),
        );
    }

    public function test_stats_wrapper_rejects_rows_with_missing_fields(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/admin/dashboard/models*' => Http::response([
                'code' => 0,
                'data' => [
                    'models' => [[
                        'model' => 'gpt-test',
                        'requests' => 1,
                    ]],
                ],
            ]),
        ]);

        $this->expectException(Sub2ApiStatsException::class);
        $this->expectExceptionMessage('Sub2API 官方统计响应字段异常');
        app(Sub2ApiAdminClient::class)->dashboardModels(
            ChinaDateRange::make('2026-07-01', '2026-07-02'),
        );
    }

    public function test_balance_history_route_keeps_working(): void
    {
        $this->withoutMiddleware();
        Http::fake([
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
                        'notes' => Sub2ApiNoteTag::make('ADJ-88', 'idem-88'),
                    ]],
                    'total' => 1,
                ],
            ]),
        ]);

        $this->getJson('/api/v1/sub2api/users/1001/balance-history?page=1&page_size=8')
            ->assertOk()
            ->assertJsonPath('items.0.id', 88)
            ->assertJsonPath('items.0.operation', 'decrement')
            ->assertJsonPath('items.0.value', '-5.00')
            ->assertJsonPath('items.0.adjusted_account', 'alpha@example.com')
            ->assertJsonPath('items.0.before_balance', '50.00')
            ->assertJsonPath('items.0.after_balance', '45.00')
            ->assertJsonPath('items.0.notes', null);
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
