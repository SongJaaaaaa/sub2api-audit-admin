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
                'adjust_reason' => '人工充值',
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
            && $req['balance'] === '10.00'
            && $req['operation'] === LedgerAdjustment::OP_INCREMENT
            && str_contains((string) $req['notes'], 'ledger_no=ADJ'));
    }

    public function test_sub2api_call_failure_voids_adjustment_and_does_not_list_as_success(): void
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
            'adjust_reason' => '人工充值',
        ]);

        $res->assertStatus(409)
            ->assertJsonPath('adjustment.status', LedgerAdjustment::STATUS_VOIDED);

        $this->assertDatabaseHas('ledger_adjustments', [
            'sub2api_user_id' => 1001,
            'status' => LedgerAdjustment::STATUS_VOIDED,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/ledger-adjustments')
            ->assertOk()
            ->assertJsonPath('total', 0);
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
                'adjust_reason' => '人工充值',
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
