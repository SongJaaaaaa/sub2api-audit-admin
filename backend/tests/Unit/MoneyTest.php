<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_large_decimals_keep_their_difference(): void
    {
        $this->assertSame(-1, Money::compare('9007199254740992.01', '9007199254740992.02', 8));
        $this->assertSame('0.01000000', Money::abs(Money::sub('9007199254740992.01', '9007199254740992.02', 8), 8));
    }

    public function test_scaled_sum_does_not_use_float(): void
    {
        $this->assertSame('9007199254740992.03000000', Money::sum([
            '9007199254740992.01',
            '0.02',
        ], 8));
    }
}
