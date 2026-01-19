<?php

namespace DhruvilNagar\ActionEngine\Support;

use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Illuminate\Support\Facades\Cache;

/**
 * RateLimiter
 * 
 * Manages rate limiting for bulk action executions to prevent system abuse
 * and resource exhaustion.
 * 
 * Implements two complementary limiting strategies:
 * 1. Concurrent Action Limit: Maximum number of simultaneous active actions per user
 * 2. Cooldown Period: Required waiting time between action initiations
 * 
 * Rate limiting is configurable per-user and can be globally disabled.
 * All limits are enforced using cache-based tracking for performance.
 * 
 * Configuration options in config/action-engine.php:
 * - rate_limiting.enabled: Global on/off switch
 * - rate_limiting.max_concurrent_actions: Concurrent action limit
 * - rate_limiting.cooldown_seconds: Cooldown duration after large actions
 */
class RateLimiter
{
    /**
     * Check if a user can initiate a new bulk action.
     * 
     * Validates both concurrent action limits and cooldown periods.
     * Returns true if the user is within limits, false otherwise.
     *
     * @param mixed $user The user attempting to execute an action (User model or null)
     * @return bool True if action is allowed, false if rate limited
     */
    public function attempt($user): bool
    {
        if (!config('action-engine.rate_limiting.enabled', true)) {
            return true;
        }

        if (!$user) {
            return true;
        }

        // Check concurrent actions limit
        if (!$this->checkConcurrentLimit($user)) {
            return false;
        }

        // Check cooldown
        if (!$this->checkCooldown($user)) {
            return false;
        }

        return true;
    }

    /**
     * Verify user is within concurrent action execution limit.
     * 
     * Counts active executions (pending or processing status) and compares
     * against the configured maximum.
     *
     * @param mixed $user The user to check
     * @return bool True if under limit, false if at or over limit
     */
    protected function checkConcurrentLimit($user): bool
    {
        $maxConcurrent = config('action-engine.rate_limiting.max_concurrent_actions', 5);

        $activeCount = BulkActionExecution::forUser($user)
            ->whereIn('status', [
                BulkActionExecution::STATUS_PENDING,
                BulkActionExecution::STATUS_PROCESSING,
            ])
            ->count();

        return $activeCount < $maxConcurrent;
    }

    /**
     * Check if user's cooldown period has expired.
     * 
     * Cooldown is enforced after large bulk operations to prevent
     * rapid successive executions that could overwhelm the system.
     *
     * @param mixed $user The user to check
     * @return bool True if cooldown expired (can proceed), false if still in cooldown
     */
    protected function checkCooldown($user): bool
    {
        $cooldownSeconds = config('action-engine.rate_limiting.cooldown_seconds', 60);
        $cacheKey = "bulk_action_cooldown_{$user->getKey()}";

        if (Cache::has($cacheKey)) {
            return false;
        }

        return true;
    }

    /**
     * Impose a cooldown period on a user.
     * 
     * Should be called after large bulk operations complete to enforce
     * a waiting period before the next action can be initiated.
     *
     * @param mixed $user The user to apply cooldown to
     * @param int|null $seconds Duration in seconds (null uses config default)
     * @return void
     */
    public function setCooldown($user, int $seconds = null): void
    {
        if (!$user) {
            return;
        }

        $cooldownSeconds = $seconds ?? config('action-engine.rate_limiting.cooldown_seconds', 60);
        $cacheKey = "bulk_action_cooldown_{$user->getKey()}";

        Cache::put($cacheKey, true, now()->addSeconds($cooldownSeconds));
    }

    /**
     * Clear cooldown for a user.
     */
    public function clearCooldown($user): void
    {
        if (!$user) {
            return;
        }

        $cacheKey = "bulk_action_cooldown_{$user->getKey()}";
        Cache::forget($cacheKey);
    }

    /**
     * Get remaining cooldown time in seconds.
     */
    public function getCooldownRemaining($user): int
    {
        if (!$user) {
            return 0;
        }

        $cacheKey = "bulk_action_cooldown_{$user->getKey()}";
        
        // This is a simplified version - actual TTL retrieval depends on cache driver
        if (!Cache::has($cacheKey)) {
            return 0;
        }

        // Return configured cooldown as approximation
        return config('action-engine.rate_limiting.cooldown_seconds', 60);
    }

    /**
     * Get the number of active actions for a user.
     */
    public function getActiveCount($user): int
    {
        if (!$user) {
            return 0;
        }

        return BulkActionExecution::forUser($user)
            ->whereIn('status', [
                BulkActionExecution::STATUS_PENDING,
                BulkActionExecution::STATUS_PROCESSING,
            ])
            ->count();
    }

    /**
     * Get the remaining slots for concurrent actions.
     */
    public function getRemainingSlots($user): int
    {
        $maxConcurrent = config('action-engine.rate_limiting.max_concurrent_actions', 5);
        $activeCount = $this->getActiveCount($user);

        return max(0, $maxConcurrent - $activeCount);
    }
}
