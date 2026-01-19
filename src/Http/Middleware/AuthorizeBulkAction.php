<?php

namespace DhruvilNagar\ActionEngine\Http\Middleware;

use Closure;
use DhruvilNagar\ActionEngine\Exceptions\UnauthorizedBulkActionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeBulkAction
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        if (!config('action-engine.authorization.enabled', true)) {
            return $next($request);
        }

        $user = $request->user();
        
        if (!$user) {
            throw new UnauthorizedBulkActionException('Authentication required.');
        }

        // Check default gate if specified
        $defaultGate = $ability ?? config('action-engine.authorization.default_gate');
        
        if ($defaultGate && !Gate::forUser($user)->allows($defaultGate)) {
            throw new UnauthorizedBulkActionException('You are not authorized to perform bulk actions.');
        }

        return $next($request);
    }
}
