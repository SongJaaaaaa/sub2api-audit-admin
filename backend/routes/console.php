<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('ledger:reconcile')
    ->dailyAt('00:15')
    ->timezone(config('ledger.timezone', 'Asia/Shanghai'))
    ->withoutOverlapping();
