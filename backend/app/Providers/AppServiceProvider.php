<?php

namespace App\Providers;

use App\Services\Ledger\RebateWithdrawalPayoutService;
use App\Services\Rebate\WithdrawalPayoutService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WithdrawalPayoutService::class, RebateWithdrawalPayoutService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
