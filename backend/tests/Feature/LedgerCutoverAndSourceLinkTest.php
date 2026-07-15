<?php

namespace Tests\Feature;

use App\Jobs\Rebate\ProcessRebateWithdrawal;
use App\Models\Admin;
use App\Models\LedgerAdjustment;
use App\Models\Rebate\RebateUser;
use App\Models\Rebate\RebateWithdrawal;
use App\Models\SystemSetting;
use App\Services\Ledger\LedgerAdjustmentService;
use App\Services\Ledger\LedgerCutoverService;
use App\Services\Ledger\LedgerSourceLinkService;
use App\Services\Ledger\RebateWithdrawalPayoutService;
use App\Services\Rebate\BalanceService;
use App\Services\Rebate\WithdrawalService;
use App\Support\Sub2ApiNoteTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Sub2ApiTestDatabase;
use Tests\TestCase;

class LedgerCutoverAndSourceLinkTest extends TestCase
{
    use RefreshDatabase;
    use Sub2ApiTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSub2ApiDatabase();
        config()->set('sub2api.admin_api.base_url', 'https://sub2api.test');
        config()->set('sub2api.admin_api.key', 'test-key');
    }

    protected function tearDown(): void
    {
        $this->tearDownSub2ApiDatabase();

        parent::tearDown();
    }

    public function test_cutover_command_saves_utc_once_and_permanently_rejects_changes(): void
    {
        $this->artisan('ledger:cutover', ['--at' => '2026-07-01 10:30:00'])
            ->expectsOutputToContain('中国时间：2026-07-01 10:30:00')
            ->expectsOutputToContain('UTC：2026-07-01T02:30:00.000000Z')
            ->assertSuccessful();

        $setting = SystemSetting::query()->findOrFail(LedgerCutoverService::KEY);
        $this->assertSame('2026-07-01T02:30:00.000000Z', $setting->value);
        $this->assertNotNull($setting->locked_at);

        $this->artisan('ledger:cutover', ['--at' => '2026-07-02 00:00:00'])
            ->expectsOutputToContain('切账时间已锁定')
            ->expectsOutputToContain('当前中国时间：2026-07-01 10:30:00')
            ->expectsOutputToContain('当前 UTC：2026-07-01T02:30:00.000000Z')
            ->assertFailed();

        $this->assertSame('2026-07-01T02:30:00.000000Z', app(LedgerCutoverService::class)->get()?->format('Y-m-d\TH:i:s.u\Z'));
        $this->assertSame(1, SystemSetting::query()->count());
    }

    public function test_source_link_only_saves_a_unique_user_and_full_key_match(): void
    {
        $admin = $this->admin();
        $unique = $this->local($admin, 1, 1001, 'exact-key');
        $none = $this->local($admin, 2, 1001, 'missing-key');
        $many = $this->local($admin, 3, 1002, 'duplicate-key');
        $sameLedger = $this->local($admin, 4, 1003, 'ledger-key', 'ADJ-SAME');

        $this->remote(10, 1001, Sub2ApiNoteTag::make('REMOTE', 'exact-key'));
        $this->remote(11, 1002, Sub2ApiNoteTag::make('REMOTE-A', 'duplicate-key'));
        $this->remote(12, 1002, Sub2ApiNoteTag::make('REMOTE-B', 'duplicate-key'));
        $this->remote(13, 1003, Sub2ApiNoteTag::make('ADJ-SAME', 'different-key'));
        $this->remote(14, 9999, Sub2ApiNoteTag::make('REMOTE', 'exact-key'));
        $this->remote(15, 1001, Sub2ApiNoteTag::make('REMOTE', 'exact-key-extra'));

        $linker = app(LedgerSourceLinkService::class);

        $this->assertSame(10, $linker->link($unique));
        $this->assertNull($linker->link($none));
        $this->assertNull($linker->link($many));
        $this->assertNull($linker->link($sameLedger));
        $this->assertSame(10, $unique->refresh()->sub2api_source_id);
        $this->assertNull($none->refresh()->sub2api_source_id);
        $this->assertNull($many->refresh()->sub2api_source_id);
        $this->assertNull($sameLedger->refresh()->sub2api_source_id);
    }

    public function test_backfill_command_uses_the_same_source_link_rules(): void
    {
        $admin = $this->admin();
        $linked = $this->local($admin, 1, 1001, 'backfill-key');
        $unlinked = $this->local($admin, 2, 1002, 'missing-key');
        $this->remote(20, 1001, Sub2ApiNoteTag::make('REMOTE', 'backfill-key'));

        $this->artisan('ledger:link-sources')
            ->expectsOutputToContain('处理 2 条，成功关联 1 条，仍未关联 1 条。')
            ->assertSuccessful();

        $this->assertSame(20, $linked->refresh()->sub2api_source_id);
        $this->assertNull($unlinked->refresh()->sub2api_source_id);
    }

    public function test_successful_adjustment_confirms_balance_then_links_remote_event(): void
    {
        $admin = $this->admin();
        $userSeq = Http::sequence()
            ->push(['data' => ['id' => 1001, 'email' => 'alpha@example.com', 'balance' => '50.00']])
            ->push(['data' => ['id' => 1001, 'email' => 'alpha@example.com', 'balance' => '60.00']]);

        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => $userSeq,
            'https://sub2api.test/api/v1/admin/users/1001/balance' => function (Request $req) {
                DB::connection('sub2api')->table('redeem_codes')->insert([
                    'id' => 900,
                    'type' => 'admin_balance',
                    'value' => '10.00',
                    'status' => 'used',
                    'used_by' => 1001,
                    'used_at' => '2026-07-10 04:00:00',
                    'notes' => (string) $req['notes'],
                    'created_at' => '2026-07-10 04:00:00',
                ]);

                return Http::response([
                    'code' => 0,
                    'data' => ['id' => 1001, 'balance' => '60.00'],
                ]);
            },
        ]);

        $res = $this->withToken($admin->createToken('adjustment')->plainTextToken)
            ->postJson('/api/v1/ledger-adjustments', [
                'sub2api_user_id' => 1001,
                'operation' => LedgerAdjustment::OP_INCREMENT,
                'amount' => '10.00',
                'cash_amount' => '10.00',
                'adjust_reason' => '充值',
                'admin_notes' => '线下已收款',
            ])
            ->assertCreated()
            ->assertJsonPath('adjustment.status', LedgerAdjustment::STATUS_SUCCEEDED)
            ->assertJsonPath('adjustment.before_balance', '50.00')
            ->assertJsonPath('adjustment.after_balance', '60.00')
            ->assertJsonPath('adjustment.sub2api_source_id', 900);

        $adj = LedgerAdjustment::query()->findOrFail($res->json('adjustment.id'));
        $this->assertSame(900, $adj->sub2api_source_id);
        $this->assertSame($adj->idempotency_key, Sub2ApiNoteTag::idempotencyKey($adj->sub2api_notes));

        Http::assertSent(function (Request $req) use ($adj): bool {
            if ($req->url() !== 'https://sub2api.test/api/v1/admin/users/1001/balance') {
                return false;
            }

            return $req->hasHeader('Idempotency-Key', $adj->idempotency_key)
                && Sub2ApiNoteTag::idempotencyKey($req['notes']) === $adj->idempotency_key;
        });
    }

    public function test_lost_response_waits_for_delayed_remote_ledger_without_a_second_adjustment(): void
    {
        $admin = $this->admin();
        config()->set('ledger.remote_reconcile_delay_seconds', 60);
        $userSeq = Http::sequence()
            ->push(['data' => ['id' => 1001, 'email' => 'alpha@example.com', 'balance' => '50.00']])
            ->push(['data' => ['id' => 1001, 'email' => 'alpha@example.com', 'balance' => '60.00']]);
        $postCalls = 0;
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => $userSeq,
            'https://sub2api.test/api/v1/admin/users/1001/balance' => function () use (&$postCalls) {
                $postCalls++;

                throw new ConnectionException('response lost');
            },
        ]);
        $service = app(LedgerAdjustmentService::class);
        $data = [
            'sub2api_user_id' => 1001,
            'operation' => LedgerAdjustment::OP_INCREMENT,
            'amount' => '10.00',
            'cash_amount' => '0.00',
            'gift_quota_amount' => '0.00',
            'adjust_reason' => '返利提现',
            'admin_notes' => '返利提现 RBW-1',
        ];

        $first = $service->adjustBusiness(
            $admin,
            $data,
            LedgerAdjustment::BUSINESS_REBATE_WITHDRAWAL,
            '1',
            'rebate-withdrawal-1',
        );
        $second = $service->adjustBusiness(
            $admin,
            $data,
            LedgerAdjustment::BUSINESS_REBATE_WITHDRAWAL,
            '1',
            'rebate-withdrawal-1',
        );

        $this->assertSame(LedgerAdjustment::STATUS_EXCEPTION, $first->status);
        $this->assertSame('Sub2API 幂等流水尚未可见，请稍后重试', $second->exception_reason);
        $this->remote(901, 1001, Sub2ApiNoteTag::make('RBW1', 'rebate-withdrawal-1'));

        $third = $service->adjustBusiness(
            $admin,
            $data,
            LedgerAdjustment::BUSINESS_REBATE_WITHDRAWAL,
            '1',
            'rebate-withdrawal-1',
        );

        $this->assertSame($first->id, $third->id);
        $this->assertSame(LedgerAdjustment::STATUS_SUCCEEDED, $third->status);
        $this->assertSame(901, $third->sub2api_source_id);
        $this->assertSame('0.00', $third->cash_amount);
        $this->assertSame('0.00', $third->gift_quota_amount);
        $this->assertSame(1, $postCalls);
    }

    public function test_withdrawal_lost_response_reuses_remote_adjustment_and_completes_frozen_balance(): void
    {
        Queue::fake();
        config()->set('sub2api.admin_api.idempotency_verified', true);
        config()->set('ledger.remote_reconcile_delay_seconds', 60);
        $admin = $this->admin();
        $user = RebateUser::query()->create([
            'id' => 1001,
            'email' => 'affiliate@example.com',
            'username' => 'affiliate',
            'status' => RebateUser::STATUS_ACTIVE,
            'aff_code' => 'AFF1001',
        ]);
        $balances = app(BalanceService::class);
        $withdrawals = app(WithdrawalService::class);
        $balances->credit($user->id, '20.00', 'test', 'withdrawal-opening');
        $withdrawal = $withdrawals->request($user, '10.00');
        $withdrawals->approve($withdrawal, $admin->id);

        $postCalls = 0;
        $userSeq = Http::sequence()
            ->push(['data' => ['id' => 1001, 'email' => $user->email, 'balance' => '50.00']])
            ->push(['data' => ['id' => 1001, 'email' => $user->email, 'balance' => '60.00']]);
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => $userSeq,
            'https://sub2api.test/api/v1/admin/users/1001/balance' => function () use (&$postCalls) {
                $postCalls++;

                throw new ConnectionException('response lost');
            },
        ]);
        $payout = app(RebateWithdrawalPayoutService::class);
        $job = new ProcessRebateWithdrawal($withdrawal->id);

        $job->handle($payout, $balances);

        $this->assertSame(RebateWithdrawal::STATUS_EXCEPTION, $withdrawal->refresh()->status);
        $this->assertSame('10.00', $balances->get($user->id)->refresh()->frozen_amount);
        $this->assertSame(1, $postCalls);
        $key = 'rebate-withdrawal-'.$withdrawal->id;
        $this->remote(902, $user->id, Sub2ApiNoteTag::make('REMOTE-RBW', $key));

        $withdrawals->retry($withdrawal, $admin->id);
        $job->handle($payout, $balances);

        $balance = $balances->get($user->id)->refresh();
        $adjustment = LedgerAdjustment::query()->where('idempotency_key', $key)->firstOrFail();
        $this->assertSame(RebateWithdrawal::STATUS_SUCCEEDED, $withdrawal->refresh()->status);
        $this->assertSame('0.00', $balance->frozen_amount);
        $this->assertSame('10.00', $balance->withdrawn_amount);
        $this->assertSame(1, $postCalls);
        $this->assertSame(LedgerAdjustment::STATUS_SUCCEEDED, $adjustment->status);
        $this->assertSame(LedgerAdjustment::BUSINESS_REBATE_WITHDRAWAL, $adjustment->business_source);
        $this->assertSame((string) $withdrawal->id, $adjustment->business_id);
        $this->assertSame('0.00', $adjustment->cash_amount);
        $this->assertSame('0.00', $adjustment->gift_quota_amount);
        $this->assertSame(1, LedgerAdjustment::query()->where('idempotency_key', $key)->count());
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

    private function local(
        Admin $admin,
        int $seq,
        int $userId,
        string $key,
        ?string $ledgerNo = null,
    ): LedgerAdjustment {
        return LedgerAdjustment::query()->create([
            'ledger_no' => $ledgerNo ?? 'ADJ-'.$seq,
            'idempotency_key' => $key,
            'sub2api_user_id' => $userId,
            'operation' => LedgerAdjustment::OP_INCREMENT,
            'amount' => '10.00',
            'cash_amount' => '10.00',
            'gift_quota_amount' => '0.00',
            'status' => LedgerAdjustment::STATUS_SUCCEEDED,
            'adjust_reason' => '充值',
            'created_by' => $admin->id,
            'confirmed_at' => '2026-07-10 12:00:00',
        ]);
    }

    private function remote(int $id, int $userId, string $notes): void
    {
        DB::connection('sub2api')->table('redeem_codes')->insert([
            'id' => $id,
            'type' => 'admin_balance',
            'value' => '10.00',
            'status' => 'used',
            'used_by' => $userId,
            'used_at' => '2026-07-10 04:00:00',
            'notes' => $notes,
            'created_at' => '2026-07-10 04:00:00',
        ]);
    }
}
