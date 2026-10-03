<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In      = 'in';
    case Out     = 'out';
    case Reserve = 'reserve';
    case Release = 'release';
    case Commit  = 'commit';
}
