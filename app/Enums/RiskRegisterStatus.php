<?php

namespace App\Enums;

enum RiskRegisterStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Closed = 'closed';
}
