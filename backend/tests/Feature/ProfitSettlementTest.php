<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CashEntry;
use App\Models\OperationExpense;
use App\Models\ProfitSettlement;
use App\Models\ProfitSettlementItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_summary_groups_new_unsettled_rows_by_date_and_admin(): void
    {
        $first = $this->admin('爱吃胡萝卜');
        $second = $this->admin('牛宝');
        $this->cash($first, '100.00', '2026-07-04 09:00:00', false);
        $this->cash($first, '3200.00', '2026-07-04 10:00:00');
        $this->cash($second, '460.00', '2026-07-04 11:00:00');
        $this->expense($first, '1500.00', '2026-07-04');
        $this->expense($second, '5000.00', '2026-07-04');
        $this->expense($first, '100.00', '2026-07-05');

        $token = $first->createToken('profit')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/v1/profit/summary?start_date=2026-07-04&end_date=2026-07-05')
            ->assertOk()
            ->assertJsonCount(2, 'owners')
            ->assertJsonCount(2, 'days')
            ->assertJsonPath('days.0.income_total', '3660.00')
            ->assertJsonPath('days.0.expense_total', '6500.00')
            ->assertJsonPath('days.0.profit_total', '-2840.00')
            ->assertJsonPath('days.1.income_total', '0.00')
            ->assertJsonPath('days.1.expense_total', '100.00')
            ->assertJsonPath('summary.income_total', '3660.00')
            ->assertJsonPath('summary.expense_total', '6600.00')
            ->assertJsonPath('summary.profit_total', '-2940.00')
            ->assertJsonPath('summary.income_count', 2)
            ->assertJsonPath('summary.expense_count', 3);
    }

    public function test_confirm_late_entry_and_reverse_flow(): void
    {
        CarbonImmutable::setTestNow('2026-07-15 12:00:00 Asia/Shanghai');
        $admin = $this->admin('爱吃胡萝卜');
        $cash = $this->cash($admin, '3200.00', '2026-07-04 10:00:00');
        $expense = $this->expense($admin, '1500.00', '2026-07-04');
        $token = $admin->createToken('profit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/profit/settlements', [
            'start_date' => '2026-07-04',
            'end_date' => '2026-07-04',
        ])->assertCreated()
            ->assertJsonPath('settlement.income_total', '3200.00')
            ->assertJsonPath('settlement.expense_total', '1500.00')
            ->assertJsonPath('settlement.profit_total', '1700.00')
            ->assertJsonPath('settlement.status', ProfitSettlement::STATUS_CONFIRMED);

        $batchId = (int) $response->json('settlement.id');
        $this->assertSame($batchId, $cash->refresh()->profit_settlement_id);
        $this->assertSame($batchId, $expense->refresh()->profit_settlement_id);
        $this->assertDatabaseCount('profit_settlement_items', 2);
        $this->assertDatabaseHas('profit_settlement_items', ['item_type' => ProfitSettlementItem::TYPE_INCOME, 'amount' => '3200.00']);

        $this->withToken($token)
            ->postJson('/api/v1/profit/settlements', ['start_date' => '2026-07-04', 'end_date' => '2026-07-04'])
            ->assertStatus(422)
            ->assertJsonPath('message', '该日期范围没有待分账流水');

        $late = $this->cash($admin, '500.00', '2026-07-04 18:00:00');
        $this->withToken($token)
            ->getJson('/api/v1/profit/summary?start_date=2026-07-04&end_date=2026-07-04')
            ->assertOk()
            ->assertJsonPath('summary.income_total', '500.00')
            ->assertJsonPath('summary.expense_total', '0.00');

        $this->withToken($token)->postJson("/api/v1/profit/settlements/{$batchId}/reverse")
            ->assertOk()
            ->assertJsonPath('settlement.status', ProfitSettlement::STATUS_REVERSED);

        $this->assertNull($cash->refresh()->profit_settlement_id);
        $this->assertNull($expense->refresh()->profit_settlement_id);
        $this->assertNull($late->refresh()->profit_settlement_id);
        $this->assertDatabaseCount('profit_settlement_items', 2);
        $this->withToken($token)
            ->getJson('/api/v1/profit/summary?start_date=2026-07-04&end_date=2026-07-04')
            ->assertJsonPath('summary.income_total', '3700.00')
            ->assertJsonPath('summary.expense_total', '1500.00');

        $this->withToken($token)->postJson("/api/v1/profit/settlements/{$batchId}/reverse")
            ->assertStatus(422)
            ->assertJsonPath('message', '该分账批次已撤销');
    }

    public function test_validation_auth_and_batch_items(): void
    {
        $admin = $this->admin('牛宝');
        $this->cash($admin, '100.00', '2026-07-06 09:00:00');
        $token = $admin->createToken('profit')->plainTextToken;

        $this->getJson('/api/v1/profit/summary?start_date=2026-07-06&end_date=2026-07-06')->assertUnauthorized();
        $this->withToken($token)->getJson('/api/v1/profit/summary?start_date=2026-07-07&end_date=2026-07-06')->assertStatus(422);
        $batch = $this->withToken($token)->postJson('/api/v1/profit/settlements', [
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-06',
        ])->json('settlement');

        $this->withToken($token)->getJson('/api/v1/profit/settlements')
            ->assertOk()
            ->assertJsonPath('items.0.batch_no', $batch['batch_no']);
        $this->withToken($token)->getJson('/api/v1/profit/settlements/'.$batch['id'].'/items')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.owner_name', '牛宝');
    }

    private function admin(string $name): Admin
    {
        return Admin::query()->create([
            'name' => $name,
            'email' => uniqid('admin').'@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);
    }

    private function cash(Admin $admin, string $amount, string $createdAt, bool $eligible = true): CashEntry
    {
        $row = CashEntry::query()->create([
            'entry_no' => uniqid('CASH'),
            'direction' => CashEntry::DIR_IN,
            'cash_amount' => $amount,
            'source' => 'ledger_adjustment',
            'profit_eligible' => $eligible,
            'created_by' => $admin->id,
        ]);
        $row->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $row;
    }

    private function expense(Admin $admin, string $amount, string $date): OperationExpense
    {
        return OperationExpense::query()->create([
            'expense_no' => uniqid('EXP'),
            'category' => '服务器',
            'amount' => $amount,
            'paid_at' => $date,
            'profit_eligible' => true,
            'created_by' => $admin->id,
        ]);
    }
}
