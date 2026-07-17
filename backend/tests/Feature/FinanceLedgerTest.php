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

        $this->assertDatabaseHas('cash_entries', ['sub2api_user_id' => 1001, 'cash_amount' => '100.00', 'profit_eligible' => true]);
        $this->assertDatabaseHas('gift_quota_entries', ['sub2api_user_id' => 1001, 'quota_amount' => '20.00']);

        $this->withToken($token)->getJson('/api/v1/finance/cash?page_size=1')
            ->assertOk()
            ->assertJsonPath('items.0.cash_amount', '100.00')
            ->assertJsonPath('summary.record_count', 1)
            ->assertJsonPath('summary.user_count', 1)
            ->assertJsonPath('summary.amount_total', '100.00')
            ->assertJsonPath('summary.linked_count', 1)
            ->assertJsonPath('summary.unlinked_count', 0);
        $this->withToken($token)->getJson('/api/v1/finance/gifts?page_size=1')
            ->assertOk()
            ->assertJsonPath('items.0.quota_amount', '20.00')
            ->assertJsonPath('items.0.has_related_cash', true)
            ->assertJsonPath('summary.record_count', 1)
            ->assertJsonPath('summary.amount_total', '20.00')
            ->assertJsonPath('summary.related_cash_count', 1)
            ->assertJsonPath('summary.missing_cash_count', 0);
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
        $this->assertDatabaseHas('operation_expenses', ['category' => '服务器', 'profit_eligible' => true]);

        $this->withToken($token)->postJson('/api/v1/finance/expenses', [
            'category' => '办公',
            'amount' => '20.00',
            'paid_at' => '2026-07-07',
            'remark' => '文具',
        ])->assertCreated();

        $this->withToken($token)
            ->getJson('/api/v1/finance/expenses?from=2026-07-06&to=2026-07-07&min_amount=20&max_amount=30')
            ->assertOk()
            ->assertJsonPath('summary.record_count', 2)
            ->assertJsonPath('summary.category_count', 2)
            ->assertJsonPath('summary.amount_total', '50.00')
            ->assertJsonPath('summary.max_amount', '30.00')
            ->assertJsonPath('summary.daily_average', '25.00')
            ->assertJsonPath('categories.0.category', '服务器')
            ->assertJsonPath('categories.0.amount_total', '30.00');

        $this->withToken($token)->getJson('/api/v1/finance/expenses?keyword=文具')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.category', '办公');
    }

    public function test_operation_expense_accepts_editor_image_data_url(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('admin-token')->plainTextToken;
        $html = '<p>账单图片</p><img src="data:image/png;base64,'.str_repeat('A', 2_796_000).'">';

        $response = $this->withToken($token)->postJson('/api/v1/finance/expenses', [
            'category' => '服务器',
            'amount' => '30.00',
            'paid_at' => '2026-07-06',
            'content_html' => $html,
        ])->assertCreated();

        $this->assertSame($html, $response->json('expense.content_html'));
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

    public function test_batch_gift_does_not_record_revenue_by_default(): void
    {
        $admin = $this->admin();
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => $this->sub2ApiUserSequence('50.00', '60.00'),
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response(['data' => ['balance' => '60.00']]),
        ]);

        $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->postJson('/api/v1/ledger-adjustments/batch-gift', [
                'user_ids' => [1001],
                'amount' => '10.00',
            ])
            ->assertCreated()
            ->assertJsonPath('items.0.adjustment.cash_amount', '0.00')
            ->assertJsonPath('items.0.adjustment.gift_quota_amount', '10.00');

        $this->assertDatabaseCount('cash_entries', 0);
        $this->assertDatabaseHas('gift_quota_entries', ['sub2api_user_id' => 1001, 'quota_amount' => '10.00']);
    }

    public function test_batch_gift_can_record_revenue(): void
    {
        $admin = $this->admin();
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => $this->sub2ApiUserSequence('50.00', '60.00'),
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response(['data' => ['balance' => '60.00']]),
        ]);

        $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->postJson('/api/v1/ledger-adjustments/batch-gift', [
                'user_ids' => [1001],
                'amount' => '10.00',
                'include_revenue' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('items.0.adjustment.cash_amount', '10.00')
            ->assertJsonPath('items.0.adjustment.gift_quota_amount', '0.00');

        $this->assertDatabaseHas('cash_entries', [
            'sub2api_user_id' => 1001,
            'cash_amount' => '10.00',
            'profit_eligible' => true,
        ]);
        $this->assertDatabaseCount('gift_quota_entries', 0);
    }

    public function test_batch_gift_rejects_invalid_revenue_flag(): void
    {
        $admin = $this->admin();

        $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->postJson('/api/v1/ledger-adjustments/batch-gift', [
                'user_ids' => [1001],
                'amount' => '10.00',
                'include_revenue' => 'yes',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('include_revenue');
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
