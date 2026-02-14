<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check if a client-feature flag is enabled.
 * Usage: ->middleware('feature:domains')
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (!config("client-features.{$feature}", false)) {
            abort(404);
        }
        return $next($request);
    }
}
