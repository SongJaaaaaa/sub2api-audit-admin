<?php

namespace App\Services\Rebate;

use App\Models\Rebate\RebateWithdrawal;

interface WithdrawalPayoutService
{
    /**
     * @return array{ok: bool, reference: ?string, response: array, error: ?string}
     */
    public function pay(RebateWithdrawal $withdrawal): array;
}
