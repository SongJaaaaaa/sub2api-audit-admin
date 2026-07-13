<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\LedgerAdjustment;
use App\Services\Ledger\LedgerCutoverService;
use App\Support\Sub2ApiNoteTag;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Sub2ApiTestDatabase;
use Tests\TestCase;

class BalanceEventsTest extends TestCase
{
    use RefreshDatabase;
    use Sub2ApiTestDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-10 12:00:00', 'Asia/Shanghai'));
        $this->setUpSub2ApiDatabase();
        app(LedgerCutoverService::class)->setOnce('2026-07-02 10:30:00');
        $this->token = $this->admin()->createToken('balance-events')->plainTextToken;
        $this->seedEvents();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        $this->tearDownSub2ApiDatabase();

        parent::tearDown();
    }

    public function test_default_history_range_is_cutover_preceding_thirty_days(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/balance-events')
            ->assertOk()
            ->assertJsonPath('range.start_date', '2026-06-03')
            ->assertJsonPath('range.end_date', '2026-07-02')
            ->assertJsonPath('range.timezone', 'Asia/Shanghai')
            ->assertJsonPath('cutover_at', '2026-07-02 10:30:00')
            ->assertJsonPath('total', 4);
    }

    public function test_history_current_and_all_use_exact_cutover_intersections(): void
    {
        $base = '/api/v1/balance-events?start_date=2026-07-02&end_date=2026-07-02&page_size=100&period=';

        $history = $this->withToken($this->token)->getJson($base.'history')->assertOk();
        $current = $this->withToken($this->token)->getJson($base.'current')->assertOk();
        $all = $this->withToken($this->token)->getJson($base.'all')->assertOk();

        $this->assertSame([
            'admin_adjustment:103',
            'admin_adjustment:101',
            'payment_order:201',
            'balance_redeem:111',
        ], $this->keys($history->json('items')));
        $this->assertSame([
            'payment_order:202',
            'balance_redeem:112',
            'admin_adjustment:104',
            'admin_adjustment:102',
        ], $this->keys($current->json('items')));
        $this->assertSame(8, $all->json('total'));

        $at = collect($current->json('items'))->firstWhere('remote_event_id', 102);
        $this->assertSame('2026-07-02 10:30:00', $at['event_at']);
        $this->assertSame('decrement', $at['direction']);
        $this->assertSame('5', $at['amount']);
    }

    public function test_sources_link_status_direction_user_and_keyword_filters(): void
    {
        $base = '/api/v1/balance-events?start_date=2026-07-02&end_date=2026-07-02&period=all&page_size=100';

        $this->withToken($this->token)->getJson($base.'&source=payment_order')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('items.0.remote_event_id', 202)
            ->assertJsonPath('items.1.remote_event_id', 201);

        $linked = $this->withToken($this->token)->getJson($base.'&link_status=linked')
            ->assertOk()
            ->assertJsonPath('total', 2);
        $this->assertSame([102, 101], collect($linked->json('items'))->pluck('remote_event_id')->all());
        $this->assertSame(['ADJ-LEGACY', 'ADJ-SOURCE'], collect($linked->json('items'))->pluck('ledger_no')->all());

        $this->withToken($this->token)->getJson($base.'&link_status=audit_orphan')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.remote_event_id', 103)
            ->assertJsonPath('items.0.ledger_no', null);

        $decrements = $this->withToken($this->token)->getJson($base.'&direction=decrement')
            ->assertOk()
            ->assertJsonPath('total', 2);
        $this->assertSame([
            'balance_redeem:112',
            'admin_adjustment:102',
        ], $this->keys($decrements->json('items')));

        $this->withToken($this->token)->getJson($base.'&user_id=1001')
            ->assertOk()
            ->assertJsonPath('total', 4);
        $this->withToken($this->token)->getJson($base.'&keyword='.urlencode('张三'))
            ->assertOk()
            ->assertJsonPath('total', 4);
        $this->withToken($this->token)->getJson($base.'&keyword=li@example.com')
            ->assertOk()
            ->assertJsonPath('total', 4);
    }

    public function test_source_linked_adjustment_is_not_reused_by_legacy_key(): void
    {
        $this->redeem(
            105,
            'admin_balance',
            1001,
            '2.00',
            '2026-07-02 02:20:00',
            Sub2ApiNoteTag::make('REMOTE-DUPLICATE', 'source-key'),
        );
        $url = '/api/v1/balance-events?start_date=2026-07-02&end_date=2026-07-02&period=all&source=admin_adjustment&page_size=100';
        $items = collect($this->withToken($this->token)->getJson($url)->assertOk()->json('items'));
        $source = $items->firstWhere('remote_event_id', 101);
        $duplicate = $items->firstWhere('remote_event_id', 105);

        $this->assertSame('linked', $source['link_status']);
        $this->assertSame('ADJ-SOURCE', $source['ledger_no']);
        $this->assertSame('audit_orphan', $duplicate['link_status']);
        $this->assertNull($duplicate['ledger_no']);
    }

    public function test_payment_orders_only_include_completed_balance_nonzero_events(): void
    {
        $url = '/api/v1/balance-events?start_date=2026-07-02&end_date=2026-07-02&period=all&source=payment_order&page_size=100';
        $res = $this->withToken($this->token)->getJson($url)->assertOk();

        $this->assertSame([202, 201], collect($res->json('items'))->pluck('remote_event_id')->all());
        $this->assertNotContains(203, collect($res->json('items'))->pluck('remote_event_id')->all());
        $this->assertNotContains(204, collect($res->json('items'))->pluck('remote_event_id')->all());
        $this->assertNotContains(205, collect($res->json('items'))->pluck('remote_event_id')->all());
        $this->assertNotContains(206, collect($res->json('items'))->pluck('remote_event_id')->all());
    }

    public function test_pagination_and_csv_share_filters_while_export_ignores_pagination(): void
    {
        $filters = 'start_date=2026-07-02&end_date=2026-07-02&period=all&source=admin_adjustment&link_status=linked';
        $page = $this->withToken($this->token)
            ->getJson('/api/v1/balance-events?'.$filters.'&page=1&page_size=1')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('summary.record_count', 2)
            ->assertJsonPath('summary.user_count', 2)
            ->assertJsonPath('summary.increment_total', '10.00')
            ->assertJsonPath('summary.decrement_total', '5.00')
            ->assertJsonPath('summary.net_total', '5.00')
            ->assertJsonPath('summary.linked_count', 2)
            ->assertJsonPath('summary.linked_rate', 100);

        $csvRes = $this->withToken($this->token)
            ->get('/api/v1/balance-events/export?'.$filters.'&page=999999&page_size=999999')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('balance-events-2026-07-02-2026-07-02.csv');
        $csv = $csvRes->streamedContent();

        $this->assertSame("\xEF\xBB\xBF", substr($csv, 0, 3));
        $this->assertStringContainsString('事件时间,来源,远端事件ID,用户ID,用户邮箱,用户名,方向,金额,关联状态,本地单号,备注', $csv);
        $this->assertStringContainsString('"2026-07-02 10:30:00",admin_adjustment,102,1002,li@example.com,李四,decrement,5,linked,ADJ-LEGACY', $csv);
        $this->assertStringContainsString('"2026-07-02 10:00:00",admin_adjustment,101,1001,zhang@example.com,张三,increment,10,linked,ADJ-SOURCE', $csv);
        $this->assertSame(3, substr_count(trim($csv), "\n") + 1);
        $this->assertSame(102, $page->json('items.0.remote_event_id'));
    }

    public function test_date_pair_and_filter_validation_returns_422(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/balance-events?start_date=2026-07-01')
            ->assertStatus(422);
        $this->withToken($this->token)
            ->getJson('/api/v1/balance-events?start_date=2026-07-03&end_date=2026-07-02')
            ->assertStatus(422);
        $this->withToken($this->token)
            ->getJson('/api/v1/balance-events?period=invalid')
            ->assertStatus(422);
        $this->withToken($this->token)
            ->getJson('/api/v1/balance-events?page_size=101')
            ->assertStatus(422);
    }

    private function seedEvents(): void
    {
        $this->insertSub2ApiUser([
            'id' => 1001,
            'email' => 'zhang@example.com',
            'username' => '张三',
        ]);
        $this->insertSub2ApiUser([
            'id' => 1002,
            'email' => 'li@example.com',
            'username' => '李四',
        ]);
        $admin = Admin::query()->firstOrFail();
        $this->local($admin, 'ADJ-SOURCE', 'source-key', 1001, 101);
        $this->local($admin, 'ADJ-LEGACY', 'legacy-key', 1002);

        $this->redeem(101, 'admin_balance', 1001, '10.00', '2026-07-02 02:00:00', 'source linked');
        $this->redeem(102, 'admin_balance', 1002, '-5.00', '2026-07-02 02:30:00', Sub2ApiNoteTag::make('OTHER', 'legacy-key'));
        $this->redeem(103, 'admin_balance', 1001, '7.00', '2026-07-02 02:10:00', Sub2ApiNoteTag::make('ADJ-SOURCE', 'wrong-key'));
        $this->redeem(104, 'admin_balance', 1002, '6.00', '2026-07-02 03:00:00', '外部后台调额');
        $this->redeem(111, 'balance', 1001, '8.00', '2026-07-02 01:00:00', '余额兑换');
        $this->redeem(112, 'balance', 1002, '-3.00', '2026-07-02 04:00:00', '余额兑换冲正');

        $this->payment(201, 1001, '12.00', 'COMPLETED', 'balance', '2026-07-02 01:30:00');
        $this->payment(202, 1002, '13.00', 'completed', 'balance', '2026-07-02 05:00:00');
        $this->payment(203, 1001, '14.00', 'pending', 'balance', '2026-07-02 05:10:00');
        $this->payment(204, 1001, '15.00', 'completed', 'subscription', '2026-07-02 05:20:00');
        $this->payment(205, 1001, '0.00', 'completed', 'balance', '2026-07-02 05:30:00');
        $this->payment(206, 1001, '16.00', 'completed', 'balance', null);
    }

    private function local(Admin $admin, string $ledgerNo, string $key, int $userId, ?int $sourceId = null): void
    {
        LedgerAdjustment::query()->create([
            'ledger_no' => $ledgerNo,
            'idempotency_key' => $key,
            'sub2api_user_id' => $userId,
            'sub2api_source_id' => $sourceId,
            'operation' => LedgerAdjustment::OP_INCREMENT,
            'amount' => '10.00',
            'cash_amount' => '10.00',
            'gift_quota_amount' => '0.00',
            'status' => LedgerAdjustment::STATUS_SUCCEEDED,
            'adjust_reason' => '充值',
            'created_by' => $admin->id,
            'confirmed_at' => '2026-07-02 10:00:00',
        ]);
    }

    private function redeem(int $id, string $type, int $userId, string $value, string $usedAt, string $notes): void
    {
        DB::connection('sub2api')->table('redeem_codes')->insert([
            'id' => $id,
            'type' => $type,
            'value' => $value,
            'status' => 'used',
            'used_by' => $userId,
            'used_at' => $usedAt,
            'notes' => $notes,
            'created_at' => $usedAt,
        ]);
    }

    private function payment(
        int $id,
        int $userId,
        string $amount,
        string $status,
        string $type,
        ?string $completedAt,
    ): void {
        DB::connection('sub2api')->table('payment_orders')->insert([
            'id' => $id,
            'user_id' => $userId,
            'user_email' => 'snapshot'.$userId.'@example.com',
            'user_name' => 'snapshot-'.$userId,
            'amount' => $amount,
            'out_trade_no' => 'ORDER-'.$id,
            'order_type' => $type,
            'status' => $status,
            'completed_at' => $completedAt,
            'created_at' => $completedAt ?? '2026-07-02 05:40:00',
        ]);
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'name' => '管理员',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);
    }

    private function keys(array $items): array
    {
        return collect($items)
            ->map(fn (array $row): string => $row['source'].':'.$row['remote_event_id'])
            ->all();
    }
}
