<?php

namespace App\Services\Rebate;

use App\Models\Rebate\RebateEvent;
use App\Models\Rebate\RebateProgress;
use App\Models\Rebate\RebateRecord;
use App\Models\Rebate\RebateReferral;
use App\Models\Rebate\RebateUser;
use Illuminate\Support\Facades\DB;

class DirectRebateService
{
    public function __construct(
        private readonly ConfigService $configs,
        private readonly BalanceService $balances,
    ) {}

    public function process(RebateEvent $sourceEvent): RebateEvent
    {
        return DB::transaction(function () use ($sourceEvent): RebateEvent {
            $event = RebateEvent::query()->lockForUpdate()->findOrFail($sourceEvent->id);
            if ($event->status === RebateEvent::STATUS_SUCCEEDED) {
                return $event->load('records');
            }

            $event->update([
                'status' => RebateEvent::STATUS_PROCESSING,
                'attempts' => $event->attempts + 1,
                'error' => null,
            ]);

            RebateProgress::query()->createOrFirst(
                ['user_id' => $event->user_id],
                [
                    'total_recharge_amount' => '0.00',
                    'milestone_times' => 0,
                    'stage_times' => 0,
                ],
            );
            $progress = RebateProgress::query()
                ->where('user_id', $event->user_id)
                ->lockForUpdate()
                ->firstOrFail();
            $config = $this->configs->get();
            $snapshot = $config->only(array_keys(ConfigService::DEFAULTS));
            $afterTotal = RebateMoney::add($progress->total_recharge_amount, $event->amount);

            $milestoneReached = min(
                RebateMoney::reached($afterTotal, $config->milestone_amount),
                $config->milestone_max_times,
            );
            $milestoneTimes = max($progress->milestone_times, $milestoneReached);
            $milestoneTriggers = max(0, $milestoneTimes - $progress->milestone_times);

            $stageBase = RebateMoney::times($config->milestone_amount, $config->milestone_max_times);
            $stageAmount = RebateMoney::compare($afterTotal, $stageBase) > 0
                ? RebateMoney::sub($afterTotal, $stageBase)
                : '0.00';
            $stageReached = RebateMoney::reached($stageAmount, $config->stage_amount);
            $stageTimes = max($progress->stage_times, $stageReached);
            $stageTriggers = max(0, $stageTimes - $progress->stage_times);

            $parent = RebateReferral::query()
                ->where('user_id', $event->user_id)
                ->with('parent')
                ->first()?->parent;

            if ($parent instanceof RebateUser && $parent->isActive()) {
                $this->grant($event, $parent, RebateRecord::TYPE_MILESTONE, $milestoneTriggers, $config->milestone_reward_amount, $snapshot);
                $this->grant($event, $parent, RebateRecord::TYPE_STAGE, $stageTriggers, $config->stage_reward_amount, $snapshot);
            }

            $progress->update([
                'total_recharge_amount' => $afterTotal,
                'milestone_times' => $milestoneTimes,
                'stage_times' => $stageTimes,
                'last_event_id' => $event->id,
            ]);
            $event->update([
                'status' => RebateEvent::STATUS_SUCCEEDED,
                'processed_at' => now(),
                'error' => null,
            ]);

            return $event->refresh()->load('records');
        });
    }

    private function grant(
        RebateEvent $event,
        RebateUser $parent,
        string $type,
        int $triggerCount,
        string $unitReward,
        array $configSnapshot,
    ): void {
        if ($triggerCount === 0) {
            return;
        }

        $amount = RebateMoney::times($unitReward, $triggerCount);
        $snapshot = $configSnapshot;
        $snapshot['trigger_count'] = $triggerCount;
        $snapshot['unit_reward_amount'] = RebateMoney::normalize($unitReward);

        $record = RebateRecord::query()->create([
            'event_id' => $event->id,
            'receiver_user_id' => $parent->id,
            'payer_user_id' => $event->user_id,
            'level' => 1,
            'type' => $type,
            'source_amount' => $event->amount,
            'rebate_amount' => $amount,
            'trigger_count' => $triggerCount,
            'status' => RebateRecord::STATUS_CONFIRMED,
            'config_snapshot' => $snapshot,
            'remark' => $type === RebateRecord::TYPE_MILESTONE ? '一级里程碑返利' : '一级累充返利',
        ]);

        $this->balances->credit(
            $parent->id,
            $amount,
            'rebate_record',
            (string) $record->id,
            $record->remark,
            ['event_id' => $event->id, 'payer_user_id' => $event->user_id],
        );
    }
}
