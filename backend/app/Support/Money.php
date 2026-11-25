<?php

namespace App\Support;

class Money
{
    public static function toMinor(string|int|float $amount): int
    {
        return (int) round(
            ((float) $amount) * 100
        );
    }

    public static function fromMinor(int $amount): string
    {
        return number_format(
            $amount / 100,
            2,
            '.',
            ''
        );
    }

    public static function multiply(
        string|int|float $amount,
        int $quantity
    ): int {
        return self::toMinor($amount) * $quantity;
    }
}