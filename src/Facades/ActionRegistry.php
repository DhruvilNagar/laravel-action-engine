<?php

namespace DhruvilNagar\ActionEngine\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void register(string $name, \Closure|string $handler, array $options = [])
 * @method static \Closure|string|object get(string $name)
 * @method static bool has(string $name)
 * @method static array all()
 * @method static array getMetadata(string $name)
 * @method static void unregister(string $name)
 *
 * @see \DhruvilNagar\ActionEngine\Actions\ActionRegistry
 */
class ActionRegistry extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'action-registry';
    }
}
