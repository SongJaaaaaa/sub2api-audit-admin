<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CashEntry;
use App\Models\OperationExpense;
use App\Models\ProfitSettlement;
use App\Models\ProfitSettlementItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfitSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_summary_groups_eligible_rows_by_date_and_admin(): void
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
            ->getJson('/api/v1/profit/summary?start_date=2026-07-04&end_date=2026-07-06')
            ->assertOk()
            ->assertJsonCount(2, 'owners')
            ->assertJsonCount(3, 'days')
            ->assertJsonPath('owners.0.id', $first->id)
            ->assertJsonPath('owners.0.name', '爱吃胡萝卜')
            ->assertJsonPath('owners.0.income_total', '3200.00')
            ->assertJsonPath('owners.0.income_count', 1)
            ->assertJsonPath('owners.0.expense_total', '1600.00')
            ->assertJsonPath('owners.0.expense_count', 2)
            ->assertJsonPath('owners.1.id', $second->id)
            ->assertJsonPath('owners.1.name', '牛宝')
            ->assertJsonPath('owners.1.income_total', '460.00')
            ->assertJsonPath('owners.1.income_count', 1)
            ->assertJsonPath('owners.1.expense_total', '5000.00')
            ->assertJsonPath('owners.1.expense_count', 1)
            ->assertJsonPath('days.0.biz_date', '2026-07-04')
            ->assertJsonPath('days.0.income_by_owner.'.$first->id, '3200.00')
            ->assertJsonPath('days.0.income_by_owner.'.$second->id, '460.00')
            ->assertJsonPath('days.0.expense_by_owner.'.$first->id, '1500.00')
            ->assertJsonPath('days.0.expense_by_owner.'.$second->id, '5000.00')
            ->assertJsonPath('days.0.income_total', '3660.00')
            ->assertJsonPath('days.0.expense_total', '6500.00')
            ->assertJsonPath('days.0.profit_total', '-2840.00')
            ->assertJsonPath('days.1.biz_date', '2026-07-05')
            ->assertJsonPath('days.1.income_total', '0.00')
            ->assertJsonPath('days.1.expense_total', '100.00')
            ->assertJsonPath('days.2.biz_date', '2026-07-06')
            ->assertJsonPath('days.2.income_total', '0.00')
            ->assertJsonPath('days.2.expense_total', '0.00')
            ->assertJsonPath('days.2.profit_total', '0.00')
            ->assertJsonPath('summary.income_total', '3660.00')
            ->assertJsonPath('summary.expense_total', '6600.00')
            ->assertJsonPath('summary.profit_total', '-2940.00')
            ->assertJsonPath('summary.income_count', 2)
            ->assertJsonPath('summary.expense_count', 3)
            ->assertJsonPath('pending_summary.income_total', '3660.00')
            ->assertJsonPath('pending_summary.expense_total', '6600.00')
            ->assertJsonPath('pending_summary.profit_total', '-2940.00')
            ->assertJsonPath('pending_summary.income_count', 2)
            ->assertJsonPath('pending_summary.expense_count', 3);
    }

    public function test_summary_keeps_unknown_admin_in_owner_breakdown(): void
    {
        $admin = $this->admin('查询管理员');
        $this->cash(null, '12.34', '2026-07-07 09:00:00');
        $this->expense(null, '5.67', '2026-07-07');

        $token = $admin->createToken('profit')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/v1/profit/summary?start_date=2026-07-07&end_date=2026-07-07')
            ->assertOk()
            ->assertJsonCount(1, 'owners')
            ->assertJsonPath('owners.0.id', 0)
            ->assertJsonPath('owners.0.name', '未知管理员')
            ->assertJsonPath('owners.0.email', null)
            ->assertJsonPath('owners.0.income_total', '12.34')
            ->assertJsonPath('owners.0.income_count', 1)
            ->assertJsonPath('owners.0.expense_total', '5.67')
            ->assertJsonPath('owners.0.expense_count', 1)
            ->assertJsonPath('days.0.income_by_owner.0', '12.34')
            ->assertJsonPath('days.0.expense_by_owner.0', '5.67')
            ->assertJsonPath('summary.income_total', '12.34')
            ->assertJsonPath('summary.expense_total', '5.67')
            ->assertJsonPath('summary.profit_total', '6.67')
            ->assertJsonPath('pending_summary.income_total', '12.34')
            ->assertJsonPath('pending_summary.expense_total', '5.67')
            ->assertJsonPath('pending_summary.profit_total', '6.67');
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

        $this->withToken($token)
            ->getJson('/api/v1/profit/summary?start_date=2026-07-04&end_date=2026-07-04')
            ->assertOk()
            ->assertJsonPath('owners.0.name', '爱吃胡萝卜')
            ->assertJsonPath('days.0.income_by_owner.'.$admin->id, '3200.00')
            ->assertJsonPath('days.0.expense_by_owner.'.$admin->id, '1500.00')
            ->assertJsonPath('summary.income_total', '3200.00')
            ->assertJsonPath('summary.expense_total', '1500.00')
            ->assertJsonPath('pending_summary.income_total', '0.00')
            ->assertJsonPath('pending_summary.expense_total', '0.00')
            ->assertJsonPath('pending_summary.income_count', 0)
            ->assertJsonPath('pending_summary.expense_count', 0);
        $this->withToken($token)
            ->getJson('/api/v1/profit/details?biz_date=2026-07-04')
            ->assertOk()
            ->assertJsonCount(1, 'income')
            ->assertJsonCount(1, 'expenses')
            ->assertJsonPath('income.0.owner_name', '爱吃胡萝卜')
            ->assertJsonPath('expenses.0.owner_name', '爱吃胡萝卜');

        $late = $this->cash($admin, '500.00', '2026-07-04 18:00:00');
        $this->withToken($token)
            ->getJson('/api/v1/profit/summary?start_date=2026-07-04&end_date=2026-07-04')
            ->assertOk()
            ->assertJsonPath('summary.income_total', '3700.00')
            ->assertJsonPath('summary.expense_total', '1500.00')
            ->assertJsonPath('pending_summary.income_total', '500.00')
            ->assertJsonPath('pending_summary.expense_total', '0.00');

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
            ->assertJsonPath('summary.expense_total', '1500.00')
            ->assertJsonPath('pending_summary.income_total', '3700.00')
            ->assertJsonPath('pending_summary.expense_total', '1500.00');

        $this->withToken($token)->postJson("/api/v1/profit/settlements/{$batchId}/reverse")
            ->assertStatus(422)
            ->assertJsonPath('message', '该分账批次已撤销');
    }

    public function test_late_settlement_only_processes_pending_rows(): void
    {
        $admin = $this->admin('小铺');
        $first = $this->cash($admin, '100.00', '2026-07-09 09:00:00');
        $token = $admin->createToken('profit-pending')->plainTextToken;
        $firstBatch = $this->withToken($token)->postJson('/api/v1/profit/settlements', [
            'start_date' => '2026-07-09',
            'end_date' => '2026-07-09',
        ])->assertCreated()->json('settlement');

        $late = $this->cash($admin, '25.00', '2026-07-09 18:00:00');
        $secondBatch = $this->withToken($token)->postJson('/api/v1/profit/settlements', [
            'start_date' => '2026-07-09',
            'end_date' => '2026-07-09',
        ])->assertCreated()
            ->assertJsonPath('settlement.income_total', '25.00')
            ->assertJsonPath('settlement.income_count', 1)
            ->assertJsonPath('settlement.expense_count', 0)
            ->json('settlement');

        $this->assertSame((int) $firstBatch['id'], $first->refresh()->profit_settlement_id);
        $this->assertSame((int) $secondBatch['id'], $late->refresh()->profit_settlement_id);
        $this->assertDatabaseCount('profit_settlement_items', 2);
        $this->withToken($token)
            ->getJson('/api/v1/profit/summary?start_date=2026-07-09&end_date=2026-07-09')
            ->assertOk()
            ->assertJsonPath('owners.0.name', '小铺')
            ->assertJsonPath('summary.income_total', '125.00')
            ->assertJsonPath('pending_summary.income_total', '0.00')
            ->assertJsonPath('pending_summary.income_count', 0);
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

    public function test_postgres_large_decimal_totals_never_pass_through_float(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('大额 decimal 精度回归在 PostgreSQL 实库执行');
        }

        $admin = $this->admin('精度测试');
        $this->cash($admin, '900719925474099.91', '2026-07-08 09:00:00');
        $this->cash($admin, '0.01', '2026-07-08 10:00:00');
        $token = $admin->createToken('profit-precision')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/profit/summary?start_date=2026-07-08&end_date=2026-07-08')
            ->assertOk()
            ->assertJsonPath('owners.0.income_total', '900719925474099.92')
            ->assertJsonPath('summary.income_total', '900719925474099.92')
            ->assertJsonPath('pending_summary.income_total', '900719925474099.92');
        $this->withToken($token)->postJson('/api/v1/profit/settlements', [
            'start_date' => '2026-07-08',
            'end_date' => '2026-07-08',
        ])->assertCreated()
            ->assertJsonPath('settlement.income_total', '900719925474099.92')
            ->assertJsonPath('settlement.profit_total', '900719925474099.92');
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

    private function cash(?Admin $admin, string $amount, string $createdAt, bool $eligible = true): CashEntry
    {
        $row = CashEntry::query()->create([
            'entry_no' => uniqid('CASH'),
            'direction' => CashEntry::DIR_IN,
            'cash_amount' => $amount,
            'source' => 'ledger_adjustment',
            'profit_eligible' => $eligible,
            'created_by' => $admin?->id,
        ]);
        $row->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $row;
    }

    private function expense(?Admin $admin, string $amount, string $date): OperationExpense
    {
        return OperationExpense::query()->create([
            'expense_no' => uniqid('EXP'),
            'category' => '服务器',
            'amount' => $amount,
            'paid_at' => $date,
            'profit_eligible' => true,
            'created_by' => $admin?->id,
        ]);
    }
}
