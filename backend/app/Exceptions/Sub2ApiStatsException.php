<?php

namespace App\Exceptions;

use RuntimeException;

class Sub2ApiStatsException extends RuntimeException
{
    public const CODE = 'SUB2API_STATS_UNAVAILABLE';
}
