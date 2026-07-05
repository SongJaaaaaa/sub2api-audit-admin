<?php

namespace App\Support;

class Money
{
    public static function fmt(mixed $val): string
    {
        return number_format((float) $val, 2, '.', '');
    }

    public static function add(mixed $a, mixed $b): string
    {
        return self::fmt((float) $a + (float) $b);
    }

    public static function sub(mixed $a, mixed $b): string
    {
        return self::fmt((float) $a - (float) $b);
    }
}
