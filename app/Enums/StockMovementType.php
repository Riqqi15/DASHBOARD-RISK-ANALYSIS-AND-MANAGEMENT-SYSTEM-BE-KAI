<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Opening = 'opening';
    case Correction = 'correction';
}
