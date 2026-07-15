<?php

namespace Tests\Feature;

use App\Models\Rebate\RebateEvent;
use App\Models\Rebate\RebateProgress;
use App\Models\Rebate\RebateRecord;
use App\Models\Rebate\RebateReferral;
use App\Models\Rebate\RebateUser;
use App\Models\Rebate\RebateWithdrawal;
use App\Services\Rebate\BalanceService;
use App\Services\Rebate\DirectRebateService;
use App\Services\Rebate\EventIngestService;
use App\Services\Rebate\WithdrawalService;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Throwable;

class RebatePostgresConcurrencyTest extends TestCase
{
    public function test_postgres_locks_serialize_rebate_and_withdrawal_competition(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL concurrency test');
        }

        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $withdrawUser = $this->user(91001);
        app(BalanceService::class)->credit($withdrawUser->id, '10.00', 'test', 'opening');

        $requests = Concurrency::driver('process')->run([
            static fn (): array => self::requestWithdrawal(91001),
            static fn (): array => self::requestWithdrawal(91001),
        ]);

        $this->assertSame(1, collect($requests)->where('ok', true)->count());
        $this->assertSame(1, collect($requests)->where('ok', false)->count());
        $this->assertDatabaseCount('rebate_withdrawals', 1);
        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => 91001,
            'available_amount' => '0.00',
            'frozen_amount' => '10.00',
        ]);

        $parent = $this->user(91002);
        $payer = $this->user(91003);
        RebateReferral::query()->create(['user_id' => $payer->id, 'parent_user_id' => $parent->id]);
        $first = $this->event($payer->id, '60.00', 'pg-concurrent-1');
        $second = $this->event($payer->id, '40.00', 'pg-concurrent-2');
        $firstId = $first->id;
        $secondId = $second->id;
        $processed = Concurrency::driver('process')->run([
            static fn (): int => app(DirectRebateService::class)->process(RebateEvent::query()->findOrFail($firstId))->id,
            static fn (): int => app(DirectRebateService::class)->process(RebateEvent::query()->findOrFail($secondId))->id,
        ]);

        $this->assertCount(2, $processed);
        $this->assertSame('100.00', RebateProgress::query()->where('user_id', $payer->id)->value('total_recharge_amount'));
        $this->assertSame('15.00', RebateRecord::query()->where('receiver_user_id', $parent->id)->sum('rebate_amount'));

        $reviewUser = $this->user(91004);
        app(BalanceService::class)->credit($reviewUser->id, '10.00', 'test', 'review-opening');
        $withdrawal = app(WithdrawalService::class)->request($reviewUser, '10.00');
        $withdrawalId = $withdrawal->id;
        $reviews = Concurrency::driver('process')->run([
            static fn (): array => self::reviewWithdrawal($withdrawalId, true),
            static fn (): array => self::reviewWithdrawal($withdrawalId, false),
        ]);

        $this->assertSame(1, collect($reviews)->where('ok', true)->count());
        $this->assertSame(1, collect($reviews)->where('ok', false)->count());
        $status = $withdrawal->refresh()->status;
        $this->assertContains($status, [RebateWithdrawal::STATUS_PROCESSING, RebateWithdrawal::STATUS_REJECTED]);
        $balance = app(BalanceService::class)->get($reviewUser->id)->refresh();
        if ($status === RebateWithdrawal::STATUS_PROCESSING) {
            $this->assertSame('10.00', $balance->frozen_amount);
        } else {
            $this->assertSame('10.00', $balance->available_amount);
        }
    }

    public static function requestWithdrawal(int $userId): array
    {
        usleep(250_000);

        try {
            $withdrawal = app(WithdrawalService::class)->request(RebateUser::query()->findOrFail($userId), '10.00');

            return ['ok' => true, 'id' => $withdrawal->id];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function reviewWithdrawal(int $withdrawalId, bool $approve): array
    {
        usleep(250_000);
        Queue::fake();

        try {
            $withdrawal = RebateWithdrawal::query()->findOrFail($withdrawalId);
            $result = $approve
                ? app(WithdrawalService::class)->approve($withdrawal)
                : app(WithdrawalService::class)->reject($withdrawal, null, '并发拒绝');

            return ['ok' => true, 'status' => $result->status];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function user(int $id): RebateUser
    {
        return RebateUser::query()->create([
            'id' => $id,
            'email' => "pg{$id}@example.com",
            'status' => RebateUser::STATUS_ACTIVE,
            'aff_code' => 'PG'.$id,
        ]);
    }

    private function event(int $userId, string $amount, string $sourceId): RebateEvent
    {
        return RebateEvent::query()->create([
            'source_type' => EventIngestService::SOURCE_NATIVE_RECHARGE,
            'source_id' => $sourceId,
            'user_id' => $userId,
            'amount' => $amount,
            'happened_at' => now(),
            'status' => RebateEvent::STATUS_PENDING,
        ]);
    }
}
