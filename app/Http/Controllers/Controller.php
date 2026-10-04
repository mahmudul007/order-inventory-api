<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    protected const MAX_PER_PAGE = 100;

    protected function perPage(Request $request, int $default = 15): int
    {
        return max(1, min((int) $request->integer('per_page', $default), self::MAX_PER_PAGE));
    }
}
