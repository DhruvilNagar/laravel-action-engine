<?php

namespace DhruvilNagar\ActionEngine\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder on(string $modelClass)
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder action(string $actionName)
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder where(string|\Closure $column, mixed $operator = null, mixed $value = null)
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder whereIn(string $column, array $values)
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder ids(array $ids)
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder with(array $parameters)
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder batchSize(int $size)
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder sync()
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder queue()
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder scheduleFor(string|\Carbon\Carbon $datetime)
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder dryRun()
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder withUndo(int $expiryDays = 7)
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder authorize(\Closure $callback)
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder onProgress(\Closure $callback)
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder onComplete(\Closure $callback)
 * @method static \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder onFailure(\Closure $callback)
 * @method static \DhruvilNagar\ActionEngine\Models\BulkActionExecution execute()
 * @method static int count()
 * @method static \Illuminate\Support\Collection preview(int $limit = 10)
 * @method static \DhruvilNagar\ActionEngine\Actions\ActionChain chain(array $actions)
 *
 * @see \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder
 */
class BulkAction extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'bulk-action';
    }
}
