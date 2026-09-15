<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MobileOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('user_access.mobile_only', false)) {
            return $next($request);
        }

        // The administration panel is intentionally designed for desktop use.
        if ($request->is('admin') || $request->is('admin/*')) {
            return $next($request);
        }

        if (! $this->isMobile($request)) {
            return response()->view('mobile-only', status: 403);
        }

        return $next($request);
    }

    private function isMobile(Request $request): bool
    {
        $clientHint = $request->header('Sec-CH-UA-Mobile');
        if ($clientHint !== null) {
            return trim($clientHint) === '?1';
        }

        $userAgent = $request->userAgent() ?? '';

        return (bool) preg_match(
            '/Mobile|iPhone|iPod|Android.+Mobile|Windows Phone|IEMobile|Opera Mini|BlackBerry|webOS/i',
            $userAgent,
        );
    }
}
