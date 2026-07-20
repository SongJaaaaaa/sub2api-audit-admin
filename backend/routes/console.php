<?php

use App\Services\Ledger\FinanceLedgerService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    $today = now(config('ledger.timezone', 'Asia/Shanghai'))->toDateString();
    app(FinanceLedgerService::class)->syncExternalIncome($today, $today);
})->name('finance:sync-sub2api-income')->everyMinute()->withoutOverlapping();
