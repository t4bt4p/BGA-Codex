<?php

namespace App\Http\Middleware;

use App\Services\OverdueAccountService;
use Closure;
use Illuminate\Http\Request;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        app(OverdueAccountService::class)->suspendIfOverdue($user);
        if ((int) $user->User_status !== 1) {
            return response()->json(app(OverdueAccountService::class)->suspensionDetails($user), 403);
        }

        return $next($request);
    }
}
