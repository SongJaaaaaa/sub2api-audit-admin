<?php

namespace App\Exceptions;

use RuntimeException;

class LedgerCutoverException extends RuntimeException
{
    public const CODE = 'LEDGER_CUTOVER_UNAVAILABLE';
}
