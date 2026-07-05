<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\LedgerAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileTest extends TestCase
{
    use RefreshDatabase;

    public function test_balanced_day_can_be_reconciled_only_once(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('admin-token')->plainTextToken;

        LedgerAdjustment::query()->create([
            'ledger_no' => 'ADJ202607060001',
            'idempotency_key' => 'key-1',
            'sub2api_user_id' => 1001,
            'operation' => LedgerAdjustment::OP_INCREMENT,
            'amount' => '120.00',
            'cash_amount' => '100.00',
            'gift_quota_amount' => '20.00',
            'before_balance' => '50.00',
            'after_balance' => '170.00',
            'status' => LedgerAdjustment::STATUS_SUCCEEDED,
            'adjust_reason' => '充值',
            'created_by' => $admin->id,
            'confirmed_at' => '2026-07-05 16:10:00',
        ]);

        $this->withToken($token)->postJson('/api/v1/reconciliations', [
            'biz_date' => '2026-07-06',
        ])->assertCreated()
            ->assertJsonPath('batch.status', 'balanced')
            ->assertJsonPath('batch.diff_amount', '0.00');

        $this->withToken($token)->postJson('/api/v1/reconciliations', [
            'biz_date' => '2026-07-06',
        ])->assertStatus(409);
    }

    public function test_non_zero_diff_is_not_marked_balanced(): void
    {
        $admin = $this->admin();

        LedgerAdjustment::query()->create([
            'ledger_no' => 'ADJ202607060002',
            'idempotency_key' => 'key-2',
            'sub2api_user_id' => 1002,
            'operation' => LedgerAdjustment::OP_INCREMENT,
            'amount' => '120.00',
            'before_balance' => '50.00',
            'after_balance' => '160.00',
            'status' => LedgerAdjustment::STATUS_SUCCEEDED,
            'adjust_reason' => '充值',
            'created_by' => $admin->id,
            'confirmed_at' => '2026-07-05 16:10:00',
        ]);

        $token = $admin->createToken('admin-token')->plainTextToken;
        $res = $this->withToken($token)->postJson('/api/v1/reconciliations', ['biz_date' => '2026-07-06'])
            ->assertCreated()
            ->assertJsonPath('batch.status', 'diff');

        $this->withToken($token)->getJson('/api/v1/reconciliations/'.$res->json('batch.id').'/diffs')
            ->assertOk()
            ->assertJsonPath('items.0.amount', '10.00');
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
}
