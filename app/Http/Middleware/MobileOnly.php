<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MobileOnly
{
    public function handle(Request $request, Closure $next)
    {
        // Responsive UI remains usable from desktop for administration and testing.
        return $next($request);
    }
}
