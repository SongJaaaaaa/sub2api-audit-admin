<?php

namespace App\Services\Rebate;

use App\Models\Rebate\RebateRecord;
use App\Support\ChinaTime;
use Carbon\CarbonImmutable;

class RebateTrendService
{
    public function lastSevenDays(?int $receiverUserId = null): array
    {
        $timezone = config('ledger.timezone', 'Asia/Shanghai');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $start = $today->subDays(6);
        $amounts = [];

        for ($day = $start; $day->lessThanOrEqualTo($today); $day = $day->addDay()) {
            $amounts[$day->toDateString()] = '0.00';
        }

        $query = RebateRecord::query()
            ->select(['created_at', 'rebate_amount'])
            ->where('created_at', '>=', $start->format(ChinaTime::FORMAT))
            ->where('created_at', '<', $today->addDay()->format(ChinaTime::FORMAT));

        if ($receiverUserId !== null) {
            $query->where('receiver_user_id', $receiverUserId);
        }

        foreach ($query->cursor() as $record) {
            $date = $record->created_at->setTimezone($timezone)->toDateString();
            $amounts[$date] = bcadd($amounts[$date], (string) $record->rebate_amount, 2);
        }

        $items = [];
        foreach ($amounts as $date => $amount) {
            $items[] = compact('date', 'amount');
        }

        return $items;
    }
}
