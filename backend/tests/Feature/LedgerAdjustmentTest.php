<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\LedgerAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LedgerAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjustment_succeeds_only_after_sub2api_confirmed(): void
    {
        $admin = $this->admin();
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => $this->sub2ApiUserSequence('50.00', '60.00'),
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response([
                'data' => ['id' => 1001, 'balance' => '60.00'],
            ]),
        ]);

        $res = $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->postJson('/api/v1/ledger-adjustments', [
                'sub2api_user_id' => 1001,
                'operation' => LedgerAdjustment::OP_INCREMENT,
                'amount' => '10',
                'adjust_reason' => '充值',
                'admin_notes' => '线下已收款',
            ]);

        $res->assertCreated()
            ->assertJsonPath('adjustment.status', LedgerAdjustment::STATUS_SUCCEEDED)
            ->assertJsonPath('adjustment.before_balance', '50.00')
            ->assertJsonPath('adjustment.after_balance', '60.00');

        $this->assertDatabaseHas('ledger_adjustments', [
            'sub2api_user_id' => 1001,
            'status' => LedgerAdjustment::STATUS_SUCCEEDED,
            'before_balance' => '50.00',
            'after_balance' => '60.00',
        ]);

        Http::assertSent(fn ($req): bool => $req->url() === 'https://sub2api.test/api/v1/admin/users/1001/balance'
            && $req->hasHeader('Idempotency-Key')
            && (float) $req['balance'] === 10.0
            && $req['operation'] === 'add'
            && str_contains((string) $req['notes'], 'ledger_no=ADJ'));
    }

    public function test_sub2api_call_failure_marks_exception_and_does_not_list_as_success(): void
    {
        $admin = $this->admin();
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => $this->sub2ApiUserSequence('50.00'),
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response([], 500),
        ]);

        $token = $admin->createToken('admin-token')->plainTextToken;
        $res = $this->withToken($token)->postJson('/api/v1/ledger-adjustments', [
            'sub2api_user_id' => 1001,
            'operation' => LedgerAdjustment::OP_INCREMENT,
            'amount' => '10.00',
            'adjust_reason' => '充值',
        ]);

        $res->assertStatus(409)
            ->assertJsonPath('adjustment.status', LedgerAdjustment::STATUS_EXCEPTION);

        $this->assertDatabaseHas('ledger_adjustments', [
            'sub2api_user_id' => 1001,
            'status' => LedgerAdjustment::STATUS_EXCEPTION,
        ]);
        $this->assertNotNull(LedgerAdjustment::query()->firstOrFail()->request_started_at);

        $this->withToken($token)
            ->getJson('/api/v1/ledger-adjustments')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_list_filters_email_operator_and_date_and_returns_summary(): void
    {
        $admin = $this->admin();
        $other = Admin::query()->create([
            'name' => '其他管理员',
            'email' => 'other@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);

        foreach ([
            [$admin, 1001, 'alpha@example.com', '10.00', '2026-07-10 12:00:00'],
            [$admin, 1002, 'beta@example.com', '20.00', '2026-07-11 12:00:00'],
            [$other, 1003, 'alpha-other@example.com', '30.00', '2026-07-10 12:00:00'],
        ] as [$creator, $userId, $email, $amount, $time]) {
            LedgerAdjustment::query()->create([
                'ledger_no' => 'ADJ-'.$userId,
                'idempotency_key' => 'key-'.$userId,
                'sub2api_user_id' => $userId,
                'sub2api_user_email' => $email,
                'operation' => LedgerAdjustment::OP_INCREMENT,
                'amount' => $amount,
                'cash_amount' => $amount,
                'gift_quota_amount' => '0.00',
                'status' => LedgerAdjustment::STATUS_SUCCEEDED,
                'adjust_reason' => '充值',
                'created_by' => $creator->id,
                'confirmed_at' => $time,
            ]);
        }

        $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->getJson('/api/v1/ledger-adjustments?sub2api_user_email=alpha&created_by='.$admin->id.'&start_date=2026-07-10&end_date=2026-07-10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('summary.record_count', 1)
            ->assertJsonPath('summary.user_count', 1)
            ->assertJsonPath('summary.amount_total', '10.00')
            ->assertJsonPath('summary.increment_total', '10.00')
            ->assertJsonPath('summary.decrement_total', '0.00')
            ->assertJsonPath('summary.net_total', '10.00')
            ->assertJsonPath('summary.cash_total', '10.00')
            ->assertJsonPath('summary.gift_total', '0.00')
            ->assertJsonPath('items.0.operator_name', '管理员')
            ->assertJsonPath('items.0.operator_email', 'admin@example.com');
    }

    public function test_summary_uses_all_filtered_rows_when_page_is_limited(): void
    {
        $admin = $this->admin();
        foreach ([
            [1001, LedgerAdjustment::OP_INCREMENT, '100.00', '80.00', '20.00'],
            [1002, LedgerAdjustment::OP_DECREMENT, '80.00', '0.00', '0.00'],
        ] as [$userId, $operation, $amount, $cash, $gift]) {
            LedgerAdjustment::query()->create([
                'ledger_no' => 'ADJ-'.$userId,
                'idempotency_key' => 'key-'.$userId,
                'sub2api_user_id' => $userId,
                'operation' => $operation,
                'amount' => $amount,
                'cash_amount' => $cash,
                'gift_quota_amount' => $gift,
                'status' => LedgerAdjustment::STATUS_SUCCEEDED,
                'adjust_reason' => '测试',
                'created_by' => $admin->id,
                'confirmed_at' => '2026-07-10 12:00:00',
            ]);
        }

        $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->getJson('/api/v1/ledger-adjustments?page_size=1')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('summary.record_count', 2)
            ->assertJsonPath('summary.increment_total', '100.00')
            ->assertJsonPath('summary.decrement_total', '80.00')
            ->assertJsonPath('summary.net_total', '20.00')
            ->assertJsonPath('summary.cash_total', '80.00')
            ->assertJsonPath('summary.gift_total', '20.00');
    }

    public function test_confirm_failure_marks_exception(): void
    {
        $admin = $this->admin();
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => $this->sub2ApiUserSequence('50.00', '50.00'),
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response([
                'data' => ['id' => 1001, 'balance' => '60.00'],
            ]),
        ]);

        $res = $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->postJson('/api/v1/ledger-adjustments', [
                'sub2api_user_id' => 1001,
                'operation' => LedgerAdjustment::OP_INCREMENT,
                'amount' => '10.00',
                'adjust_reason' => '充值',
            ]);

        $res->assertStatus(409)
            ->assertJsonPath('adjustment.status', LedgerAdjustment::STATUS_EXCEPTION)
            ->assertJsonPath('adjustment.exception_reason', 'Sub2API 二次确认余额不一致');

        $this->assertDatabaseHas('ledger_adjustments', [
            'sub2api_user_id' => 1001,
            'status' => LedgerAdjustment::STATUS_EXCEPTION,
            'after_balance' => '50.00',
        ]);
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
            $seq->push([
                'data' => [
                    'id' => 1001,
                    'email' => 'alpha@example.com',
                    'balance' => $balance,
                ],
            ]);
        }

        return $seq;
    }
}
