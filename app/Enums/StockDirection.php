<?php

namespace App\Enums;

enum StockDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function apply(int $stock, int $quantity): int
    {
        return $this === self::In ? $stock + $quantity : $stock - $quantity;
    }
}
