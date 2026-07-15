<?php

namespace Tests\Feature;

use App\Jobs\Rebate\ProcessRebateEvent;
use App\Jobs\Rebate\ProcessRebateWithdrawal;
use App\Models\Rebate\RebateBalance;
use App\Models\Rebate\RebateBalanceEntry;
use App\Models\Rebate\RebateEvent;
use App\Models\Rebate\RebateProgress;
use App\Models\Rebate\RebateRecord;
use App\Models\Rebate\RebateReferral;
use App\Models\Rebate\RebateScanCursor;
use App\Models\Rebate\RebateUser;
use App\Models\Rebate\RebateWithdrawal;
use App\Services\Ledger\RebateWithdrawalPayoutService;
use App\Services\Rebate\BalanceService;
use App\Services\Rebate\ConfigService;
use App\Services\Rebate\DirectRebateService;
use App\Services\Rebate\EventIngestService;
use App\Services\Rebate\RebateQueueRecoveryService;
use App\Services\Rebate\WithdrawalPayoutService;
use App\Services\Rebate\WithdrawalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class RebateCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_direct_parent_receives_rebate_in_a_b_c_chain(): void
    {
        $a = $this->user(1);
        $b = $this->user(2);
        $c = $this->user(3);
        $this->refer($b, $a);
        $this->refer($c, $b);
        $service = app(DirectRebateService::class);

        $service->process($this->event($b, '100.00', 'b-100'));
        $service->process($this->event($c, '100.00', 'c-100'));

        $this->assertDatabaseHas('rebate_records', [
            'payer_user_id' => $b->id,
            'receiver_user_id' => $a->id,
            'level' => 1,
            'rebate_amount' => '15.00',
        ]);
        $this->assertDatabaseHas('rebate_records', [
            'payer_user_id' => $c->id,
            'receiver_user_id' => $b->id,
            'level' => 1,
            'rebate_amount' => '15.00',
        ]);
        $this->assertDatabaseMissing('rebate_records', [
            'payer_user_id' => $c->id,
            'receiver_user_id' => $a->id,
        ]);
        $this->assertSame('15.00', RebateBalance::query()->where('user_id', $a->id)->value('available_amount'));
        $this->assertSame('15.00', RebateBalance::query()->where('user_id', $b->id)->value('available_amount'));
    }

    public function test_large_event_crosses_all_milestone_and_stage_thresholds_once(): void
    {
        $parent = $this->user(10);
        $payer = $this->user(11);
        $this->refer($payer, $parent);
        $service = app(DirectRebateService::class);
        $event = $this->event($payer, '450.00', 'large');

        $service->process($event);
        $service->process($event->refresh());

        $records = RebateRecord::query()->where('event_id', $event->id)->orderBy('type')->get();
        $this->assertCount(2, $records);
        $this->assertSame(4, $records->sum('trigger_count'));
        $this->assertSame('60.00', RebateBalance::query()->where('user_id', $parent->id)->value('available_amount'));
        $this->assertSame(2, RebateProgress::query()->where('user_id', $payer->id)->value('milestone_times'));
        $this->assertSame(2, RebateProgress::query()->where('user_id', $payer->id)->value('stage_times'));
        $this->assertSame(2, RebateBalanceEntry::query()->where('user_id', $parent->id)->count());
    }

    public function test_thresholds_without_parent_are_consumed_without_later_backfill(): void
    {
        $payer = $this->user(20);
        $service = app(DirectRebateService::class);
        $service->process($this->event($payer, '200.00', 'orphan'));

        $this->assertDatabaseCount('rebate_records', 0);
        $this->assertSame(2, RebateProgress::query()->where('user_id', $payer->id)->value('milestone_times'));

        $parent = $this->user(21);
        $this->refer($payer, $parent);
        $service->process($this->event($payer, '100.00', 'after-parent'));

        $this->assertDatabaseCount('rebate_records', 1);
        $this->assertDatabaseHas('rebate_records', [
            'receiver_user_id' => $parent->id,
            'type' => RebateRecord::TYPE_STAGE,
            'rebate_amount' => '15.00',
        ]);
    }

    public function test_inactive_direct_parent_is_not_skipped_or_backfilled(): void
    {
        $parent = $this->user(22, RebateUser::STATUS_INACTIVE);
        $payer = $this->user(23);
        $this->refer($payer, $parent);
        $service = app(DirectRebateService::class);
        $service->process($this->event($payer, '100.00', 'inactive-parent'));

        $this->assertDatabaseCount('rebate_records', 0);
        $parent->update(['status' => RebateUser::STATUS_ACTIVE]);
        $service->process($this->event($payer, '100.00', 'active-parent'));

        $this->assertDatabaseCount('rebate_records', 1);
        $this->assertSame('15.00', RebateBalance::query()->where('user_id', $parent->id)->value('available_amount'));
    }

    public function test_ingest_deduplicates_source_and_advances_cursor_atomically(): void
    {
        Queue::fake();
        app(ConfigService::class)->update(['rebate_cutover_at' => now()->subSecond()]);
        $user = $this->user(30);
        $service = app(EventIngestService::class);
        $row = [
            'source_id' => 'payment-1',
            'user_id' => $user->id,
            'amount' => '100.00',
            'happened_at' => now()->toDateTimeString(),
            'payload' => ['order_no' => 'T1'],
        ];

        $first = $service->ingest(EventIngestService::SOURCE_NATIVE_RECHARGE, [$row], 10);
        $second = $service->ingest(EventIngestService::SOURCE_NATIVE_RECHARGE, [$row], 11);

        $this->assertSame(1, $first['created_count']);
        $this->assertSame(1, $second['duplicate_count']);
        $this->assertDatabaseCount('rebate_events', 1);
        $this->assertSame('11', RebateScanCursor::query()->value('cursor_value'));
        Queue::assertPushed(ProcessRebateEvent::class, 1);
    }

    public function test_ingest_refuses_to_scan_before_cutover_is_locked(): void
    {
        $user = $this->user(31);
        $row = [
            'source_id' => 'before-cutover',
            'user_id' => $user->id,
            'amount' => '100.00',
            'happened_at' => now()->toDateTimeString(),
        ];

        try {
            app(EventIngestService::class)->ingest(EventIngestService::SOURCE_NATIVE_RECHARGE, [$row], 1);
            $this->fail('切换时间未锁定时仍执行了扫描');
        } catch (RuntimeException $e) {
            $this->assertSame('返利切换时间尚未锁定', $e->getMessage());
        }

        $this->assertDatabaseCount('rebate_events', 0);
        $this->assertDatabaseCount('rebate_scan_cursors', 0);
    }

    public function test_ingest_never_moves_a_composite_cursor_backwards(): void
    {
        app(ConfigService::class)->update(['rebate_cutover_at' => now()->subSecond()]);
        $service = app(EventIngestService::class);
        $later = json_encode(['at' => '2026-07-15 10:00:00', 'id' => 20], JSON_THROW_ON_ERROR);
        $earlier = json_encode(['at' => '2026-07-15 09:00:00', 'id' => 99], JSON_THROW_ON_ERROR);

        $service->ingest(EventIngestService::SOURCE_NATIVE_RECHARGE, [], $later);
        $service->ingest(EventIngestService::SOURCE_NATIVE_RECHARGE, [], $earlier);

        $this->assertSame($later, RebateScanCursor::query()->value('cursor_value'));
    }

    public function test_cutover_compares_remote_event_times_as_utc(): void
    {
        Queue::fake();
        app(ConfigService::class)->update([
            'rebate_cutover_at' => CarbonImmutable::parse('2026-07-15 08:00:00', 'Asia/Shanghai'),
        ]);
        $user = $this->user(32);
        $result = app(EventIngestService::class)->ingest(EventIngestService::SOURCE_NATIVE_RECHARGE, [
            [
                'source_id' => 'before-utc-cutover',
                'user_id' => $user->id,
                'amount' => '100.00',
                'happened_at' => '2026-07-14 23:59:59',
            ],
            [
                'source_id' => 'after-utc-cutover',
                'user_id' => $user->id,
                'amount' => '100.00',
                'happened_at' => '2026-07-15 00:00:01',
            ],
        ], 2);

        $this->assertSame(1, $result['created_count']);
        $this->assertSame(1, $result['skipped_count']);
        $this->assertDatabaseHas('rebate_events', ['source_id' => 'after-utc-cutover']);
        $this->assertDatabaseMissing('rebate_events', ['source_id' => 'before-utc-cutover']);
    }

    public function test_queue_recovery_dispatches_committed_pending_work(): void
    {
        Queue::fake();
        $user = $this->user(35);
        $event = $this->event($user, '100.00', 'pending-recovery');
        $withdrawal = RebateWithdrawal::query()->create([
            'withdrawal_no' => 'RBW-RECOVERY',
            'user_id' => $user->id,
            'amount' => '10.00',
            'quota_amount' => '10.00',
            'status' => RebateWithdrawal::STATUS_PROCESSING,
            'requested_at' => now(),
        ]);

        $result = app(RebateQueueRecoveryService::class)->dispatch();

        $this->assertSame(['events' => 1, 'withdrawals' => 1], $result);
        Queue::assertPushed(ProcessRebateEvent::class, fn (ProcessRebateEvent $job): bool => $job->eventId === $event->id);
        Queue::assertPushed(ProcessRebateWithdrawal::class, fn (ProcessRebateWithdrawal $job): bool => $job->withdrawalId === $withdrawal->id);
    }

    public function test_failed_event_can_be_explicitly_returned_to_the_recovery_queue(): void
    {
        Queue::fake();
        $user = $this->user(36);
        $event = $this->event($user, '100.00', 'failed-recovery');
        $event->update([
            'status' => RebateEvent::STATUS_FAILED,
            'attempts' => 3,
            'error' => 'temporary database failure',
        ]);

        $count = app(RebateQueueRecoveryService::class)->retryFailedEvents([$event->id]);

        $this->assertSame(1, $count);
        $this->assertSame(RebateEvent::STATUS_PENDING, $event->refresh()->status);
        $this->assertNull($event->error);
        Queue::assertPushed(ProcessRebateEvent::class, fn (ProcessRebateEvent $job): bool => $job->eventId === $event->id);
    }

    public function test_withdrawal_reject_unfreezes_and_success_moves_frozen_to_withdrawn(): void
    {
        Queue::fake();
        $user = $this->user(40);
        $balances = app(BalanceService::class);
        $withdrawals = app(WithdrawalService::class);
        $balances->credit($user->id, '50.00', 'test', 'opening');

        $rejected = $withdrawals->request($user, '20.00');
        $withdrawals->reject($rejected, null, '资料不符');
        $balance = $balances->get($user->id)->refresh();
        $this->assertSame('50.00', $balance->available_amount);
        $this->assertSame('0.00', $balance->frozen_amount);
        $this->assertSame(RebateWithdrawal::STATUS_REJECTED, $rejected->refresh()->status);

        $approved = $withdrawals->request($user, '20.00');
        $withdrawals->approve($approved);
        Queue::assertPushed(ProcessRebateWithdrawal::class, 1);
        $payout = new class implements WithdrawalPayoutService
        {
            public function pay(RebateWithdrawal $withdrawal): array
            {
                return [
                    'ok' => true,
                    'reference' => 'LEDGER-'.$withdrawal->id,
                    'response' => ['status' => 'succeeded'],
                    'error' => null,
                ];
            }
        };
        (new ProcessRebateWithdrawal($approved->id))->handle($payout, $balances);

        $balance = $balances->get($user->id)->refresh();
        $this->assertSame('30.00', $balance->available_amount);
        $this->assertSame('0.00', $balance->frozen_amount);
        $this->assertSame('20.00', $balance->withdrawn_amount);
        $this->assertSame(RebateWithdrawal::STATUS_SUCCEEDED, $approved->refresh()->status);
    }

    public function test_second_withdrawal_cannot_freeze_more_than_available_balance(): void
    {
        $user = $this->user(50);
        $balances = app(BalanceService::class);
        $withdrawals = app(WithdrawalService::class);
        $balances->credit($user->id, '30.00', 'test', 'opening');
        $withdrawals->request($user, '20.00');

        try {
            $withdrawals->request($user, '20.00');
            $this->fail('余额不足时仍创建了第二笔提现');
        } catch (RuntimeException $e) {
            $this->assertSame('可提现余额不足', $e->getMessage());
        }

        $balance = $balances->get($user->id)->refresh();
        $this->assertSame('10.00', $balance->available_amount);
        $this->assertSame('20.00', $balance->frozen_amount);
        $this->assertDatabaseCount('rebate_withdrawals', 1);
    }

    public function test_failed_payout_keeps_balance_frozen_until_retry_succeeds(): void
    {
        Queue::fake();
        $user = $this->user(60);
        $balances = app(BalanceService::class);
        $withdrawals = app(WithdrawalService::class);
        $balances->credit($user->id, '20.00', 'test', 'opening');
        $withdrawal = $withdrawals->request($user, '10.00');
        $withdrawals->approve($withdrawal);
        $failed = new class implements WithdrawalPayoutService
        {
            public function pay(RebateWithdrawal $withdrawal): array
            {
                return ['ok' => false, 'reference' => null, 'response' => [], 'error' => '远端超时'];
            }
        };
        (new ProcessRebateWithdrawal($withdrawal->id))->handle($failed, $balances);

        $this->assertSame(RebateWithdrawal::STATUS_EXCEPTION, $withdrawal->refresh()->status);
        $this->assertSame('10.00', $balances->get($user->id)->refresh()->frozen_amount);

        $withdrawals->retry($withdrawal);
        $succeeded = new class implements WithdrawalPayoutService
        {
            public function pay(RebateWithdrawal $withdrawal): array
            {
                return ['ok' => true, 'reference' => 'LEDGER-RETRY', 'response' => [], 'error' => null];
            }
        };
        (new ProcessRebateWithdrawal($withdrawal->id))->handle($succeeded, $balances);

        $balance = $balances->get($user->id)->refresh();
        $this->assertSame(RebateWithdrawal::STATUS_SUCCEEDED, $withdrawal->refresh()->status);
        $this->assertSame('0.00', $balance->frozen_amount);
        $this->assertSame('10.00', $balance->withdrawn_amount);
    }

    public function test_real_payout_is_blocked_until_remote_idempotency_contract_is_verified(): void
    {
        Queue::fake();
        config()->set('sub2api.admin_api.idempotency_verified', false);
        $user = $this->user(61);
        $balances = app(BalanceService::class);
        $withdrawals = app(WithdrawalService::class);
        $balances->credit($user->id, '20.00', 'test', 'opening');
        $withdrawal = $withdrawals->request($user, '10.00');
        $withdrawals->approve($withdrawal);

        $result = app(RebateWithdrawalPayoutService::class)->pay($withdrawal->refresh());

        $this->assertFalse($result['ok']);
        $this->assertSame('尚未确认 Sub2API 调额接口的 Idempotency-Key 契约', $result['error']);
        $this->assertDatabaseCount('ledger_adjustments', 0);
        $this->assertSame('10.00', $balances->get($user->id)->refresh()->frozen_amount);
    }

    private function user(int $id, string $status = RebateUser::STATUS_ACTIVE): RebateUser
    {
        return RebateUser::query()->create([
            'id' => $id,
            'username' => 'user'.$id,
            'email' => "user{$id}@example.com",
            'status' => $status,
            'aff_code' => 'AFF'.$id,
        ]);
    }

    private function refer(RebateUser $user, RebateUser $parent): void
    {
        RebateReferral::query()->create([
            'user_id' => $user->id,
            'parent_user_id' => $parent->id,
        ]);
    }

    private function event(RebateUser $user, string $amount, string $sourceId): RebateEvent
    {
        return RebateEvent::query()->create([
            'source_type' => EventIngestService::SOURCE_NATIVE_RECHARGE,
            'source_id' => $sourceId,
            'user_id' => $user->id,
            'amount' => $amount,
            'happened_at' => now(),
            'status' => RebateEvent::STATUS_PENDING,
        ]);
    }
}
