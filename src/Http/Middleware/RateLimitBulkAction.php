<?php

namespace DhruvilNagar\ActionEngine\Http\Middleware;

use Closure;
use DhruvilNagar\ActionEngine\Exceptions\RateLimitExceededException;
use DhruvilNagar\ActionEngine\Support\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitBulkAction
{
    public function __construct(
        protected RateLimiter $rateLimiter
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('action-engine.rate_limiting.enabled', true)) {
            return $next($request);
        }

        $user = $request->user();
        
        if (!$user) {
            return $next($request);
        }

        if (!$this->rateLimiter->attempt($user)) {
            $remaining = $this->rateLimiter->getCooldownRemaining($user);
            $slots = $this->rateLimiter->getRemainingSlots($user);

            throw new RateLimitExceededException(
                "Rate limit exceeded. You have {$slots} slots remaining. " .
                ($remaining > 0 ? "Please wait {$remaining} seconds." : '')
            );
        }

        return $next($request);
    }
}
