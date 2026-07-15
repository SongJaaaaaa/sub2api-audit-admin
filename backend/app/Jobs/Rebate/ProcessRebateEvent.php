<?php

namespace App\Jobs\Rebate;

use App\Models\Rebate\RebateEvent;
use App\Services\Rebate\DirectRebateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessRebateEvent implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $eventId) {}

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    public function handle(DirectRebateService $service): void
    {
        $event = RebateEvent::query()->findOrFail($this->eventId);

        try {
            $service->process($event);
        } catch (Throwable $e) {
            $latest = RebateEvent::query()->find($this->eventId);
            if ($latest && $latest->status !== RebateEvent::STATUS_SUCCEEDED) {
                $latest->update([
                    'status' => RebateEvent::STATUS_FAILED,
                    'attempts' => $latest->attempts + 1,
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}
