<?php

namespace App\Services\Rebate;

use App\Jobs\Rebate\ProcessRebateEvent;
use App\Jobs\Rebate\ProcessRebateWithdrawal;
use App\Models\Rebate\RebateEvent;
use App\Models\Rebate\RebateWithdrawal;
use Illuminate\Support\Facades\DB;

class RebateQueueRecoveryService
{
    public function dispatch(int $limit = 200): array
    {
        $limit = min(max($limit, 1), 1000);
        $events = RebateEvent::query()
            ->where('status', RebateEvent::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');
        $withdrawals = RebateWithdrawal::query()
            ->where('status', RebateWithdrawal::STATUS_PROCESSING)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $events->each(fn (int $id) => ProcessRebateEvent::dispatch($id));
        $withdrawals->each(fn (int $id) => ProcessRebateWithdrawal::dispatch($id));

        return [
            'events' => $events->count(),
            'withdrawals' => $withdrawals->count(),
        ];
    }

    public function retryFailedEvents(array $ids = [], int $limit = 100): int
    {
        $eventIds = DB::transaction(function () use ($ids, $limit) {
            $query = RebateEvent::query()
                ->where('status', RebateEvent::STATUS_FAILED)
                ->orderBy('id')
                ->limit(min(max($limit, 1), 1000));
            if ($ids !== []) {
                $query->whereIn('id', $ids);
            }

            $eventIds = $query->lockForUpdate()->pluck('id');
            RebateEvent::query()->whereIn('id', $eventIds)->update([
                'status' => RebateEvent::STATUS_PENDING,
                'error' => null,
            ]);

            return $eventIds;
        });

        $eventIds->each(fn (int $id) => ProcessRebateEvent::dispatch($id));

        return $eventIds->count();
    }
}
