<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CashEntry;
use App\Models\LedgerAdjustment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_stats_contains_recharge_quota_and_model_ranks(): void
    {
        config()->set('sub2api.db_connection', 'sqlite');
        Schema::create('usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('model');
            $table->decimal('total_cost', 18, 2);
            $table->decimal('actual_cost', 18, 2);
            $table->timestamp('created_at')->nullable();
        });
        $admin = Admin::query()->create([
            'name' => '管理员',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);
        $cash = CashEntry::query()->create([
            'entry_no' => 'CASH202607060001',
            'sub2api_user_id' => 1001,
            'sub2api_user_email' => 'alpha@example.com',
            'direction' => CashEntry::DIR_IN,
            'cash_amount' => '100.00',
            'source' => 'ledger_adjustment',
            'created_by' => $admin->id,
        ]);
        $cash->timestamps = false;
        $cash->forceFill(['created_at' => '2026-07-06 02:00:00'])->save();
        LedgerAdjustment::query()->create([
            'ledger_no' => 'ADJ202607060003',
            'idempotency_key' => 'key-3',
            'sub2api_user_id' => 1001,
            'sub2api_user_email' => 'alpha@example.com',
            'operation' => LedgerAdjustment::OP_INCREMENT,
            'amount' => '120.00',
            'status' => LedgerAdjustment::STATUS_SUCCEEDED,
            'adjust_reason' => '充值',
            'created_by' => $admin->id,
            'confirmed_at' => '2026-07-06 02:00:00',
        ]);
        \DB::table('usage_logs')->insert([
            'user_id' => 1001,
            'model' => 'gpt-4o',
            'total_cost' => '3.20',
            'actual_cost' => '2.00',
            'created_at' => '2026-07-06 02:00:00',
        ]);

        $res = $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->getJson('/api/v1/dashboard?from=2026-07-06 00:00:00&to=2026-07-06 23:59:59&model_group=gpt')
            ->assertOk();
        $res
            ->assertJsonPath('recharge_rank.0.total_amount', '100.00')
            ->assertJsonPath('quota_rank.0.total_amount', '120.00')
            ->assertJsonPath('models.0.model', 'gpt-4o');
    }
}
