<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\LedgerAdjustment;
use App\Services\Ledger\LedgerCutoverService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Sub2ApiTestDatabase;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;
    use Sub2ApiTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-03 12:00:00', 'Asia/Shanghai'));
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

    public function test_dashboard_uses_cutover_ledger_official_usage_and_separate_rankings(): void
    {
        $admin = $this->admin();
        app(LedgerCutoverService::class)->setOnce('2026-07-01 10:00:00');
        $this->seedBalanceUsers();

        $old = $this->adjustment($admin, [
            'ledger_no' => 'ADJ-OLD',
            'idempotency_key' => 'key-old',
            'amount' => '200.00',
            'cash_amount' => '100.00',
            'gift_quota_amount' => '100.00',
            'confirmed_at' => '2026-07-01 09:59:59',
        ]);
        $first = $this->adjustment($admin, [
            'ledger_no' => 'ADJ-1',
            'idempotency_key' => 'key-1',
            'sub2api_source_id' => 101,
            'amount' => '120.00',
            'cash_amount' => '100.00',
            'gift_quota_amount' => '20.00',
            'confirmed_at' => '2026-07-01 10:00:00',
        ]);
        $second = $this->adjustment($admin, [
            'ledger_no' => 'ADJ-2',
            'idempotency_key' => 'key-2',
            'sub2api_user_id' => 1002,
            'sub2api_user_email' => 'beta@example.com',
            'amount' => '50.00',
            'cash_amount' => '0.00',
            'gift_quota_amount' => '50.00',
            'confirmed_at' => '2026-07-02 23:59:59',
        ]);
        $this->adjustment($admin, [
            'ledger_no' => 'ADJ-3',
            'idempotency_key' => 'key-3',
            'sub2api_source_id' => 103,
            'operation' => LedgerAdjustment::OP_DECREMENT,
            'amount' => '30.00',
            'cash_amount' => '0.00',
            'gift_quota_amount' => '0.00',
            'confirmed_at' => '2026-07-03 00:00:00',
        ]);
        $this->adjustment($admin, [
            'ledger_no' => 'ADJ-END',
            'idempotency_key' => 'key-end',
            'sub2api_source_id' => 104,
            'amount' => '999.00',
            'cash_amount' => '999.00',
            'confirmed_at' => '2026-07-04 00:00:00',
        ]);
        $this->adjustment($admin, [
            'ledger_no' => 'ADJ-EX',
            'idempotency_key' => 'key-ex',
            'status' => LedgerAdjustment::STATUS_EXCEPTION,
            'amount' => '500.00',
            'cash_amount' => '500.00',
            'confirmed_at' => null,
            'created_at' => '2026-07-02 12:00:00',
        ]);

        $this->fakeOfficialStats();

        $res = $this->withToken($admin->createToken('dashboard')->plainTextToken)
            ->getJson('/api/v1/dashboard?start_date=2026-07-01&end_date=2026-07-03&limit=3')
            ->assertOk()
            ->assertJsonPath('range.start_date', '2026-07-01')
            ->assertJsonPath('range.end_date', '2026-07-03')
            ->assertJsonPath('range.timezone', 'Asia/Shanghai')
            ->assertJsonPath('cutover_at', '2026-07-01 10:00:00')
            ->assertJsonPath('finance.cash_total', '100.00')
            ->assertJsonPath('finance.gift_total', '70.00')
            ->assertJsonPath('finance.adjustment_in_total', '170.00')
            ->assertJsonPath('finance.adjustment_out_total', '30.00')
            ->assertJsonPath('finance.adjustment_net_total', '140.00')
            ->assertJsonPath('finance.trend.0.cash_total', '100.00')
            ->assertJsonPath('finance.trend.1.gift_total', '50.00')
            ->assertJsonPath('finance.trend.2.adjustment_out_total', '30.00')
            ->assertJsonPath('usage.request_count', 3)
            ->assertJsonPath('usage.total_tokens', 63)
            ->assertJsonPath('usage.standard_cost', '4')
            ->assertJsonPath('usage.actual_cost', '3.4')
            ->assertJsonPath('usage.trend.1.request_count', 0)
            ->assertJsonPath('balance.active_user_count', 2)
            ->assertJsonPath('balance.active_user_balance', '20')
            ->assertJsonPath('balance.total_recharged', '42')
            ->assertJsonPath('rankings.recharge_users.0.user_id', 1001)
            ->assertJsonPath('rankings.recharge_users.0.cash_total', '100.00')
            ->assertJsonPath('rankings.user_actual_cost.0.user_id', 1002)
            ->assertJsonPath('rankings.user_actual_cost.0.actual_cost', '8')
            ->assertJsonPath('rankings.user_tokens', [])
            ->assertJsonPath('rankings.models', [])
            ->assertJsonPath('alerts.unlinked_adjustment_count', 1);

        $this->assertNull($res->json('summary'));
        $this->assertNull($res->json('quota_rank'));
        $this->assertNotContains($old->id, collect($res->json('recent_adjustments'))->pluck('id')->all());
        $this->assertContains($second->id, collect($res->json('recent_adjustments'))->pluck('id')->all());
        Http::assertSentCount(3);

        Http::assertSent(function (Request $req): bool {
            if (! str_starts_with($req->url(), 'https://sub2api.test/api/v1/admin/dashboard/trend?')) {
                return false;
            }

            return $req['start_date'] === '2026-07-01'
                && $req['end_date'] === '2026-07-03'
                && $req['timezone'] === 'Asia/Shanghai'
                && $req['granularity'] === 'day';
        });
        Http::assertSent(fn (Request $req): bool => str_starts_with($req->url(), 'https://sub2api.test/api/v1/admin/dashboard/users-ranking?')
            && (int) $req['limit'] === 3);
        Http::assertSent(fn (Request $req): bool => str_starts_with($req->url(), 'https://sub2api.test/api/v1/admin/users?')
            && (int) $req['page'] === 1
            && (int) $req['page_size'] === 100);
    }

    public function test_dashboard_defaults_to_today_and_validates_date_pairs(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('dashboard')->plainTextToken;
        $this->insertSub2ApiUser();
        $this->fakeOfficialStats();

        $this->withToken($token)->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('range.start_date', '2026-07-03')
            ->assertJsonPath('range.end_date', '2026-07-03');

        $this->withToken($token)->getJson('/api/v1/dashboard?start_date=2026-07-01')
            ->assertStatus(422);
        $this->withToken($token)->getJson('/api/v1/dashboard?start_date=2026-07-03&end_date=2026-07-01')
            ->assertStatus(422);
        $this->withToken($token)->getJson('/api/v1/dashboard?limit=101')
            ->assertStatus(422);
    }

    public function test_dashboard_returns_stable_502_when_official_stats_are_unavailable(): void
    {
        $admin = $this->admin();
        $this->insertSub2ApiUser();
        Http::fake([
            'https://sub2api.test/api/v1/admin/dashboard/trend*' => Http::response(['message' => 'down'], 503),
        ]);

        $this->withToken($admin->createToken('dashboard')->plainTextToken)
            ->getJson('/api/v1/dashboard?start_date=2026-07-01&end_date=2026-07-03')
            ->assertStatus(502)
            ->assertExactJson([
                'code' => 'SUB2API_STATS_UNAVAILABLE',
                'message' => 'Sub2API 官方统计暂不可用',
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

    private function adjustment(Admin $admin, array $values): LedgerAdjustment
    {
        $row = LedgerAdjustment::query()->create(array_merge([
            'ledger_no' => 'ADJ-'.uniqid(),
            'idempotency_key' => 'key-'.uniqid(),
            'sub2api_user_id' => 1001,
            'sub2api_user_email' => 'alpha@example.com',
            'operation' => LedgerAdjustment::OP_INCREMENT,
            'amount' => '10.00',
            'cash_amount' => '10.00',
            'gift_quota_amount' => '0.00',
            'status' => LedgerAdjustment::STATUS_SUCCEEDED,
            'adjust_reason' => '充值',
            'created_by' => $admin->id,
            'confirmed_at' => '2026-07-01 12:00:00',
        ], $values));

        if (isset($values['created_at'])) {
            $row->timestamps = false;
            $row->forceFill(['created_at' => $values['created_at'], 'updated_at' => $values['created_at']])->save();
        }

        return $row;
    }

    private function seedBalanceUsers(): void
    {
        $this->insertSub2ApiUser(['id' => 1001, 'balance' => '12.5', 'total_recharged' => '10']);
        $this->insertSub2ApiUser(['id' => 1002, 'email' => 'beta@example.com', 'username' => 'beta', 'balance' => '7.5', 'total_recharged' => '20']);
        $this->insertSub2ApiUser(['id' => 1003, 'email' => 'admin@remote.test', 'role' => 'admin', 'balance' => '100', 'total_recharged' => '5']);
        $this->insertSub2ApiUser(['id' => 1004, 'email' => 'disabled@example.com', 'status' => 'disabled', 'balance' => '100', 'total_recharged' => '7']);
        $this->insertSub2ApiUser(['id' => 1005, 'email' => 'deleted@example.com', 'balance' => '100', 'total_recharged' => '9', 'deleted_at' => '2026-07-02 00:00:00']);
    }

    private function fakeOfficialStats(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/admin/dashboard/trend*' => Http::response($this->official('trend', [
                [
                    'date' => '2026-07-01',
                    'requests' => 2,
                    'input_tokens' => 10,
                    'output_tokens' => 20,
                    'cache_creation_tokens' => 3,
                    'cache_read_tokens' => 4,
                    'total_tokens' => 37,
                    'cost' => '1.5',
                    'actual_cost' => '1.2',
                ],
                [
                    'date' => '2026-07-03',
                    'requests' => 1,
                    'input_tokens' => 5,
                    'output_tokens' => 6,
                    'cache_creation_tokens' => 7,
                    'cache_read_tokens' => 8,
                    'total_tokens' => 26,
                    'cost' => '2.5',
                    'actual_cost' => '2.2',
                ],
            ])),
            'https://sub2api.test/api/v1/admin/dashboard/users-ranking*' => Http::response($this->official('ranking', [
                ['user_id' => 1001, 'email' => 'alpha@example.com', 'actual_cost' => '4', 'requests' => 10, 'tokens' => 1000],
                ['user_id' => 1002, 'email' => 'beta@example.com', 'actual_cost' => '8', 'requests' => 5, 'tokens' => 500],
            ])),
            'https://sub2api.test/api/v1/admin/users*' => Http::response($this->official('items', [
                ['role' => 'user', 'status' => 'active', 'balance' => '12.5', 'total_recharged' => '10'],
                ['role' => 'user', 'status' => 'active', 'balance' => '7.5', 'total_recharged' => '20'],
                ['role' => 'admin', 'status' => 'active', 'balance' => '100', 'total_recharged' => '5'],
                ['role' => 'user', 'status' => 'disabled', 'balance' => '100', 'total_recharged' => '7'],
            ])),
        ]);
    }

    private function official(string $field, array $rows): array
    {
        return ['code' => 0, 'message' => 'success', 'data' => [$field => $rows]];
    }
}
