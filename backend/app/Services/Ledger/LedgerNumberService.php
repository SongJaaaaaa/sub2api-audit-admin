<?php

namespace App\Services\Ledger;

use App\Models\LedgerAdjustment;
use Illuminate\Support\Str;

class LedgerNumberService
{
    public function make(): string
    {
        $date = now(config('ledger.timezone', 'Asia/Shanghai'))->format('Ymd');
        $count = LedgerAdjustment::query()
            ->where('ledger_no', 'like', "ADJ{$date}%")
            ->count();

        return 'ADJ'.$date.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    public function idempotencyKey(string $ledgerNo): string
    {
        return $ledgerNo.'-'.Str::uuid()->toString();
    }
}
