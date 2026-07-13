<?php

namespace App\Support\Finance;

final class Money
{
    /**
     * Transaction monetary fields are stored as whole-naira integers.
     */
    public static function nairaToKobo(int $nairaAmount): int
    {
        return $nairaAmount * 100;
    }

    public static function koboToNaira(int $koboAmount): int
    {
        return (int) round($koboAmount / 100);
    }
}
