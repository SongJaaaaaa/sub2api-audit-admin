<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CashEntry;
use App\Models\LedgerAdjustment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        DB::disconnect('sub2api');

        parent::tearDown();
    }

    public function test_dashboard_stats_contains_recharge_quota_and_model_ranks(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-06 12:00:00', 'Asia/Shanghai'));
        config()->set('sub2api.db_connection', 'sub2api');
        config()->set('database.connections.sub2api', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sub2api');
        $this->createSub2ApiTables();

        $admin = Admin::query()->create([
            'name' => 'Admin',
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
            'adjust_reason' => 'recharge',
            'created_by' => $admin->id,
            'confirmed_at' => '2026-07-06 02:00:00',
        ]);
        DB::connection('sub2api')->table('users')->insert([
            [
                'id' => 1001,
                'email' => 'alpha@example.com',
                'username' => 'alpha',
                'role' => 'user',
                'balance' => '12.34',
                'total_recharged' => '100.00',
                'status' => 'active',
                'created_at' => '2026-07-01 00:00:00',
                'updated_at' => '2026-07-02 00:00:00',
                'deleted_at' => null,
            ],
            [
                'id' => 1002,
                'email' => 'beta@example.com',
                'username' => 'beta',
                'role' => 'user',
                'balance' => '8.66',
                'total_recharged' => '25.00',
                'status' => 'active',
                'created_at' => '2026-07-01 00:00:00',
                'updated_at' => '2026-07-02 00:00:00',
                'deleted_at' => null,
            ],
        ]);
        DB::connection('sub2api')->table('payment_orders')->insert([
            'id' => 1,
            'user_id' => 1002,
            'amount' => '25.00',
            'order_type' => 'balance',
            'status' => 'COMPLETED',
            'completed_at' => '2026-07-06 03:00:00',
            'created_at' => '2026-07-06 02:50:00',
        ]);
        DB::connection('sub2api')->table('redeem_codes')->insert([
            [
                'id' => 1,
                'type' => 'admin_balance',
                'value' => '25.00',
                'status' => 'used',
                'used_by' => 1002,
                'used_at' => '2026-07-06 03:00:00',
                'notes' => 'Epay recharge',
                'created_at' => '2026-07-06 02:50:00',
            ],
            [
                'id' => 2,
                'type' => 'admin_balance',
                'value' => '120.00',
                'status' => 'used',
                'used_by' => 1001,
                'used_at' => '2026-07-06 02:00:01',
                'notes' => '[sub2api-audit ledger_no=ADJ202607060003]',
                'created_at' => '2026-07-06 02:00:01',
            ],
        ]);
        DB::connection('sub2api')->table('usage_logs')->insert([
            'id' => 1,
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
            ->assertJsonPath('recharge_total', '145.00')
            ->assertJsonPath('today_recharge_total', '145.00')
            ->assertJsonPath('today_summary.total_cost', '3.2')
            ->assertJsonPath('sub2api_balance_total', '21.00')
            ->assertJsonPath('recharge_rank.0.total_amount', '120.00')
            ->assertJsonPath('quota_rank.0.total_amount', '120.00')
            ->assertJsonPath('models.0.model', 'gpt-4o');
    }

    private function createSub2ApiTables(): void
    {
        Schema::connection('sub2api')->create('users', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('email');
            $table->string('username')->nullable();
            $table->string('role')->nullable();
            $table->decimal('balance', 16, 2)->default(0);
            $table->decimal('total_recharged', 16, 2)->default(0);
            $table->string('status')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::connection('sub2api')->create('usage_logs', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->string('model');
            $table->decimal('total_cost', 18, 6)->default(0);
            $table->decimal('actual_cost', 18, 6)->default(0);
            $table->timestamp('created_at');
        });


        Schema::connection('sub2api')->create('redeem_codes', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('type')->nullable();
            $table->decimal('value', 16, 2)->default(0);
            $table->string('status')->nullable();
            $table->integer('used_by')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection('sub2api')->create('payment_orders', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('user_id')->nullable();
            $table->decimal('amount', 16, 2)->default(0);
            $table->string('order_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
