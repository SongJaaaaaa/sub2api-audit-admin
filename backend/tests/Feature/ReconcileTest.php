<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\LedgerAdjustment;
use App\Models\ReconciliationBatch;
use App\Models\ReconciliationDiff;
use App\Services\Ledger\LedgerCutoverService;
use App\Services\Reconcile\ReconcileService;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use App\Support\Sub2ApiNoteTag;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Sub2ApiTestDatabase;
use Tests\TestCase;

class ReconcileTest extends TestCase
{
    use RefreshDatabase;
    use Sub2ApiTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSub2ApiDatabase();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        $this->tearDownSub2ApiDatabase();

        parent::tearDown();
    }

    public function test_reconcile_persists_all_diff_types_and_separate_warning_counts(): void
    {
        $admin = $this->admin();
        $this->cutover('2026-07-01 10:00:00');

        $this->local($admin, 1, 1001, '10.00', ['sub2api_source_id' => 101]);
        $this->local($admin, 2, 1002, '20.00', ['idempotency_key' => 'legacy-key-2']);
        $this->local($admin, 3, 1003, '30.00');
        $this->local($admin, 4, 1004, '40.00', ['sub2api_source_id' => 103]);
        $this->local($admin, 5, 1005, '50.00', ['sub2api_source_id' => 104]);
        $this->local($admin, 6, 1006, '60.00', ['sub2api_source_id' => 105]);
        $this->local($admin, 7, 1007, '70.00', [
            'sub2api_source_id' => 106,
            'idempotency_key' => 'source-key-7',
        ]);
        $this->local($admin, 8, 1007, '70.00', ['idempotency_key' => 'duplicate-key']);

        $this->remote(101, 1001, '10.00', 'source 101');
        $this->remote(102, 1002, '20.00', Sub2ApiNoteTag::make('REMOTE-2', 'legacy-key-2'));
        $this->remote(103, 9999, '40.00', 'source 103');
        $this->remote(104, 1005, '-50.00', 'source 104');
        $this->remote(105, 1006, '61.00', 'source 105');
        $this->remote(106, 1007, '70.00', Sub2ApiNoteTag::make('REMOTE-6', 'duplicate-key'));
        $this->remote(107, 1008, '5.00', '外部后台手工调额');
        $this->remote(108, 1009, '6.00', Sub2ApiNoteTag::make('ORPHAN', 'orphan-key'));

        $batch = app(ReconcileService::class)->create($admin, '2026-07-02');
        $diffs = $batch->diffs()->get();

        $this->assertSame(ReconciliationBatch::STATUS_ERROR, $batch->status);
        $this->assertSame(8, $batch->local_success_count);
        $this->assertSame('350.00000000', $batch->local_adjustment_net);
        $this->assertSame(6, $batch->remote_matched_count);
        $this->assertSame('151.00000000', $batch->remote_matched_net);
        $this->assertSame(1, $batch->external_count);
        $this->assertSame('5.00000000', $batch->external_net);
        $this->assertSame(1, $batch->audit_orphan_count);
        $this->assertSame('6.00000000', $batch->audit_orphan_net);
        $this->assertSame(5, $batch->issue_count);
        $this->assertSame('199.00', $batch->diff_amount);
        $this->assertSame([
            'amount_mismatch',
            'direction_mismatch',
            'duplicate_source_link',
            'local_missing_remote',
            'remote_audit_orphan',
            'remote_external',
            'user_mismatch',
        ], $diffs->pluck('type')->sort()->values()->all());

        $amount = $diffs->firstWhere('type', 'amount_mismatch');
        $this->assertSame('60.00000000', $amount->local_amount);
        $this->assertSame('61.00000000', $amount->remote_amount);
        $this->assertSame('1.00', $amount->amount);
    }

    public function test_source_id_has_priority_over_legacy_key(): void
    {
        $admin = $this->admin();
        $this->cutover('2026-07-01 10:00:00');
        $this->local($admin, 1, 1001, '10.00', [
            'sub2api_source_id' => 200,
            'idempotency_key' => 'matching-key',
        ]);
        $this->remote(200, 9999, '10.00', 'source id target');
        $this->remote(201, 1001, '10.00', Sub2ApiNoteTag::make('OTHER', 'matching-key'));

        $batch = app(ReconcileService::class)->create(null, '2026-07-02');

        $this->assertSame(1, $batch->remote_matched_count);
        $this->assertSame(1, $batch->audit_orphan_count);
        $this->assertSame(1, $batch->issue_count);
        $this->assertSame([
            'remote_audit_orphan',
            'user_mismatch',
        ], $batch->diffs()->pluck('type')->sort()->values()->all());
        $this->assertSame(200, $batch->diffs()->where('type', 'user_mismatch')->value('remote_event_id'));
    }

    public function test_legacy_matching_requires_user_and_complete_key_and_never_uses_ledger_number(): void
    {
        $admin = $this->admin();
        $this->cutover('2026-07-01 10:00:00');
        $this->local($admin, 1, 1001, '10.00', [
            'ledger_no' => 'ADJ-SAME',
            'idempotency_key' => 'exact-key',
        ]);
        $this->local($admin, 2, 1002, '20.00', [
            'ledger_no' => 'ADJ-LOCAL-2',
            'idempotency_key' => 'missing-key',
        ]);
        $this->remote(300, 1001, '10.00', Sub2ApiNoteTag::make('DIFFERENT-LEDGER', 'exact-key'));
        $this->remote(301, 1002, '20.00', Sub2ApiNoteTag::make('ADJ-LOCAL-2', 'wrong-key'));
        $this->remote(302, 1001, '10.00', Sub2ApiNoteTag::make('ADJ-SAME', 'exact-key-extra'));

        $batch = app(ReconcileService::class)->create(null, '2026-07-02');

        $this->assertSame(1, $batch->remote_matched_count);
        $this->assertSame(2, $batch->audit_orphan_count);
        $this->assertSame(1, $batch->issue_count);
        $this->assertSame(1, $batch->diffs()->where('type', 'local_missing_remote')->count());
        $this->assertSame(2, $batch->diffs()->where('type', 'remote_audit_orphan')->count());
    }

    public function test_rerun_reuses_batch_and_replaces_old_diffs(): void
    {
        $admin = $this->admin();
        $this->cutover('2026-07-01 10:00:00');
        $this->local($admin, 1, 1001, '10.00', ['sub2api_source_id' => 400]);
        $this->remote(400, 1001, '11.00', 'source 400');

        $first = app(ReconcileService::class)->create($admin, '2026-07-02');
        $this->assertSame(ReconciliationBatch::STATUS_ERROR, $first->status);
        $this->assertSame(1, $first->diffs()->count());

        DB::connection('sub2api')->table('redeem_codes')->where('id', 400)->update(['value' => '10.00']);
        $second = app(ReconcileService::class)->create($admin, '2026-07-02');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ReconciliationBatch::query()->count());
        $this->assertSame(ReconciliationBatch::STATUS_OK, $second->status);
        $this->assertSame(0, $second->issue_count);
        $this->assertSame(0, $second->diffs()->count());
        $this->assertSame('10.00000000', $second->remote_matched_net);
    }

    public function test_reconcile_recalculates_remote_events_inside_transaction(): void
    {
        $this->cutover('2026-07-01 10:00:00');
        $repo = \Mockery::mock(Sub2ApiReadRepository::class);
        $repo->shouldReceive('adminAdjustmentEvents')
            ->once()
            ->andReturnUsing(function (): array {
                $this->assertGreaterThan(0, DB::connection()->transactionLevel());

                return [];
            });
        $this->app->instance(Sub2ApiReadRepository::class, $repo);

        $batch = app(ReconcileService::class)->create(null, '2026-07-02');

        $this->assertSame(ReconciliationBatch::STATUS_OK, $batch->status);
        $this->assertSame(1, ReconciliationBatch::query()->count());
    }

    public function test_cutover_day_uses_exact_boundary_and_pre_cutover_date_returns_409(): void
    {
        $admin = $this->admin();
        $this->cutover('2026-07-02 10:30:00');
        $this->local($admin, 1, 1001, '9.00', [
            'sub2api_source_id' => 501,
            'confirmed_at' => '2026-07-02 10:29:59',
        ]);
        $this->local($admin, 2, 1001, '10.00', [
            'sub2api_source_id' => 502,
            'confirmed_at' => '2026-07-02 10:30:00',
        ]);
        $this->remote(501, 1001, '9.00', 'before', '2026-07-02 02:29:59');
        $this->remote(502, 1001, '10.00', 'at', '2026-07-02 02:30:00');

        $batch = app(ReconcileService::class)->create(null, '2026-07-02');

        $this->assertSame('2026-07-02 10:30:00', $batch->period_start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-03 00:00:00', $batch->period_end->format('Y-m-d H:i:s'));
        $this->assertSame(1, $batch->local_success_count);
        $this->assertSame(1, $batch->remote_matched_count);
        $this->assertSame(ReconciliationBatch::STATUS_OK, $batch->status);

        $this->withToken($admin->createToken('reconcile')->plainTextToken)
            ->postJson('/api/v1/reconciliations', ['biz_date' => '2026-07-01'])
            ->assertStatus(409)
            ->assertExactJson([
                'code' => 'LEDGER_CUTOVER_UNAVAILABLE',
                'message' => '切账前日期不生成当前对账',
            ]);
    }

    public function test_only_external_and_orphan_events_produce_warning_without_issue_count(): void
    {
        $this->cutover('2026-07-01 10:00:00');
        $this->remote(600, 1001, '5.00', 'external');
        $this->remote(601, 1002, '-2.00', Sub2ApiNoteTag::make('ORPHAN', 'orphan'));

        $batch = app(ReconcileService::class)->create(null, '2026-07-02');

        $this->assertSame(ReconciliationBatch::STATUS_WARNING, $batch->status);
        $this->assertSame(0, $batch->issue_count);
        $this->assertSame(1, $batch->external_count);
        $this->assertSame('5.00000000', $batch->external_net);
        $this->assertSame(1, $batch->audit_orphan_count);
        $this->assertSame('-2.00000000', $batch->audit_orphan_net);
    }

    public function test_list_filters_and_summary_use_all_matching_batches(): void
    {
        CarbonImmutable::setTestNow('2026-07-11 12:00:00 Asia/Shanghai');
        $admin = $this->admin();
        $rows = [
            ['B-1', '2026-07-07', ReconciliationBatch::STATUS_OK, 0, 0],
            ['B-2', '2026-07-08', ReconciliationBatch::STATUS_WARNING, 1, 0],
            ['B-3', '2026-07-09', ReconciliationBatch::STATUS_ERROR, 0, 1],
        ];
        $batches = collect($rows)->map(fn (array $row): ReconciliationBatch => ReconciliationBatch::query()->create([
            'batch_no' => $row[0],
            'biz_date' => $row[1],
            'status' => $row[2],
            'external_count' => $row[3],
            'audit_orphan_count' => $row[4],
            'created_by' => $admin->id,
        ]));
        ReconciliationDiff::query()->create([
            'reconciliation_batch_id' => $batches[1]->id,
            'type' => 'remote_external',
            'title' => '外部调额',
            'amount' => '5.00',
        ]);
        ReconciliationDiff::query()->create([
            'reconciliation_batch_id' => $batches[2]->id,
            'type' => 'amount_mismatch',
            'title' => '金额不一致',
            'amount' => '2.00',
        ]);

        $token = $admin->createToken('admin-token')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/v1/reconciliations?start_date=2026-07-08&end_date=2026-07-09&page_size=1')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('summary.batch_count', 2)
            ->assertJsonPath('summary.ok_count', 0)
            ->assertJsonPath('summary.warning_count', 1)
            ->assertJsonPath('summary.error_count', 1)
            ->assertJsonPath('summary.diff_count', 2)
            ->assertJsonPath('summary.diff_amount', '7.00')
            ->assertJsonPath('summary.healthy_rate', 0)
            ->assertJsonPath('summary.last_success_date', null)
            ->assertJsonPath('summary.unreconciled_days', 1);

        $this->withToken($token)
            ->getJson('/api/v1/reconciliations?has_external=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.status', ReconciliationBatch::STATUS_WARNING);
    }

    public function test_reconcile_command_defaults_to_previous_china_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-10 08:00:00', 'Asia/Shanghai'));
        $admin = $this->admin();
        $this->cutover('2026-07-01 10:00:00');
        $this->local($admin, 1, 1001, '10.00', [
            'sub2api_source_id' => 700,
            'confirmed_at' => '2026-07-09 12:00:00',
        ]);
        $this->remote(700, 1001, '10.00', 'source 700', '2026-07-09 04:00:00');

        $this->artisan('ledger:reconcile')
            ->expectsOutputToContain('2026-07-09 对账完成：ok')
            ->assertSuccessful();

        $batch = ReconciliationBatch::query()->firstOrFail();
        $this->assertSame('2026-07-09', $batch->biz_date->toDateString());
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'name' => '管理员',
            'email' => 'admin'.uniqid().'@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);
    }

    private function cutover(string $at): void
    {
        app(LedgerCutoverService::class)->setOnce($at);
    }

    private function local(Admin $admin, int $seq, int $userId, string $amount, array $values = []): LedgerAdjustment
    {
        return LedgerAdjustment::query()->create(array_merge([
            'ledger_no' => 'ADJ-'.$seq,
            'idempotency_key' => 'local-key-'.$seq,
            'sub2api_user_id' => $userId,
            'sub2api_user_email' => 'user'.$userId.'@example.com',
            'operation' => LedgerAdjustment::OP_INCREMENT,
            'amount' => $amount,
            'cash_amount' => $amount,
            'gift_quota_amount' => '0.00',
            'status' => LedgerAdjustment::STATUS_SUCCEEDED,
            'adjust_reason' => '测试调额',
            'created_by' => $admin->id,
            'confirmed_at' => '2026-07-02 12:00:00',
        ], $values));
    }

    private function remote(
        int $id,
        int $userId,
        string $value,
        string $notes,
        string $usedAt = '2026-07-02 04:00:00',
    ): void {
        DB::connection('sub2api')->table('redeem_codes')->insert([
            'id' => $id,
            'type' => 'admin_balance',
            'value' => $value,
            'status' => 'used',
            'used_by' => $userId,
            'used_at' => $usedAt,
            'notes' => $notes,
            'created_at' => $usedAt,
        ]);
    }
}
