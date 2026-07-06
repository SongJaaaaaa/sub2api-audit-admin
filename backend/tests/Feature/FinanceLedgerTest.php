<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\LedgerAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinanceLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_adjustment_writes_cash_and_gift_ledgers(): void
    {
        $admin = $this->admin();
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => $this->sub2ApiUserSequence('50.00', '170.00'),
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response(['data' => ['balance' => '170.00']]),
        ]);

        $token = $admin->createToken('admin-token')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/ledger-adjustments', [
            'sub2api_user_id' => 1001,
            'operation' => LedgerAdjustment::OP_INCREMENT,
            'amount' => '120.00',
            'cash_amount' => '100.00',
            'gift_quota_amount' => '20.00',
            'adjust_reason' => '充值',
        ])->assertCreated();

        $this->assertDatabaseHas('cash_entries', ['sub2api_user_id' => 1001, 'cash_amount' => '100.00']);
        $this->assertDatabaseHas('gift_quota_entries', ['sub2api_user_id' => 1001, 'quota_amount' => '20.00']);

        $this->withToken($token)->getJson('/api/v1/finance/cash')->assertOk()->assertJsonPath('items.0.cash_amount', '100.00');
        $this->withToken($token)->getJson('/api/v1/finance/gifts')->assertOk()->assertJsonPath('items.0.quota_amount', '20.00');
    }

    public function test_operation_expense_filters_rich_text(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('admin-token')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/finance/expenses', [
            'category' => '服务器',
            'amount' => '30.00',
            'paid_at' => '2026-07-06',
            'remark' => '月费',
            'content_html' => '<p onclick="bad()">账单</p><script>alert(1)</script>',
        ])->assertCreated()
            ->assertJsonMissing(['content_html' => '<p onclick="bad()">账单</p><script>alert(1)</script>']);

        $this->withToken($token)->getJson('/api/v1/finance/expenses')->assertOk()
            ->assertJsonPath('items.0.content_html', '<p>账单</p>');
    }

    public function test_correction_adjustment_does_not_write_finance_ledgers(): void
    {
        $admin = $this->admin();
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => $this->sub2ApiUserSequence('50.00', '60.00'),
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response(['data' => ['balance' => '60.00']]),
        ]);

        $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->postJson('/api/v1/ledger-adjustments', [
                'sub2api_user_id' => 1001,
                'operation' => LedgerAdjustment::OP_INCREMENT,
                'amount' => '10.00',
                'cash_amount' => '10.00',
                'adjust_reason' => '异常修正',
                'admin_notes' => '<p>修正历史差异</p>',
            ])
            ->assertCreated()
            ->assertJsonPath('adjustment.cash_amount', '0.00')
            ->assertJsonPath('adjustment.gift_quota_amount', '0.00');

        $this->assertDatabaseCount('cash_entries', 0);
        $this->assertDatabaseCount('gift_quota_entries', 0);
    }

    public function test_reissue_ignores_cash_split_and_writes_gift_only(): void
    {
        $admin = $this->admin();
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => $this->sub2ApiUserSequence('50.00', '60.00'),
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response(['data' => ['balance' => '60.00']]),
        ]);

        $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->postJson('/api/v1/ledger-adjustments', [
                'sub2api_user_id' => 1001,
                'operation' => LedgerAdjustment::OP_INCREMENT,
                'amount' => '10.00',
                'cash_amount' => '999.00',
                'adjust_reason' => '补发',
            ])
            ->assertCreated()
            ->assertJsonPath('adjustment.cash_amount', '0.00')
            ->assertJsonPath('adjustment.gift_quota_amount', '10.00');

        $this->assertDatabaseCount('cash_entries', 0);
        $this->assertDatabaseHas('gift_quota_entries', ['sub2api_user_id' => 1001, 'quota_amount' => '10.00']);
    }

    public function test_decrement_ignores_finance_split(): void
    {
        $admin = $this->admin();
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => $this->sub2ApiUserSequence('50.00', '40.00'),
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response(['data' => ['balance' => '40.00']]),
        ]);

        $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->postJson('/api/v1/ledger-adjustments', [
                'sub2api_user_id' => 1001,
                'operation' => LedgerAdjustment::OP_DECREMENT,
                'amount' => '10.00',
                'cash_amount' => '999.00',
                'adjust_reason' => '人工扣减',
            ])
            ->assertCreated()
            ->assertJsonPath('adjustment.cash_amount', '0.00')
            ->assertJsonPath('adjustment.gift_quota_amount', '0.00');

        $this->assertDatabaseCount('cash_entries', 0);
        $this->assertDatabaseCount('gift_quota_entries', 0);
    }

    private function admin(): Admin
    {
        config()->set('sub2api.admin_api.base_url', 'https://sub2api.test');
        config()->set('sub2api.admin_api.key', 'secret-key');

        return Admin::query()->create([
            'name' => '管理员',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);
    }

    private function sub2ApiUserSequence(string ...$balances): mixed
    {
        $seq = Http::sequence();
        foreach ($balances as $balance) {
            $seq->push(['data' => ['id' => 1001, 'email' => 'alpha@example.com', 'balance' => $balance]]);
        }

        return $seq;
    }
}
