<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

class StartApiSession extends StartSession
{
    public function handle($request, Closure $next)
    {
        if ($this->shouldSkipSession($request)) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    private function shouldSkipSession(Request $request): bool
    {
        $value = strtolower((string) $request->headers->get('X-Trackpal-Token-Only', ''));

        return in_array($value, ['1', 'true', 'yes'], true);
    }
}
