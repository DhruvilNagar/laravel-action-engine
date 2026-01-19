<?php

namespace DhruvilNagar\ActionEngine\Database\Factories;

use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\DhruvilNagar\ActionEngine\Models\BulkActionExecution>
 */
class BulkActionExecutionFactory extends Factory
{
    protected $model = BulkActionExecution::class;

    public function definition(): array
    {
        $totalRecords = $this->faker->numberBetween(10, 1000);
        $processedRecords = $this->faker->numberBetween(0, $totalRecords);
        $failedRecords = $this->faker->numberBetween(0, $processedRecords);

        return [
            'uuid' => Str::uuid()->toString(),
            'action_name' => $this->faker->randomElement(['delete', 'update', 'archive', 'restore', 'export']),
            'model_type' => 'App\\Models\\User',
            'filters' => [
                'where' => [
                    ['status', '=', 'inactive'],
                ],
            ],
            'parameters' => [],
            'total_records' => $totalRecords,
            'processed_records' => $processedRecords,
            'failed_records' => $failedRecords,
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed', 'cancelled']),
            'user_id' => null,
            'user_type' => null,
            'started_at' => $this->faker->optional()->dateTime(),
            'completed_at' => null,
            'scheduled_for' => null,
            'scheduled_timezone' => null,
            'error_details' => null,
            'can_undo' => false,
            'undo_expires_at' => null,
            'is_dry_run' => false,
            'dry_run_results' => null,
            'batch_size' => config('action-engine.batch_size', 500),
            'queue_connection' => null,
            'queue_name' => 'default',
            'callbacks' => null,
            'chain_config' => null,
            'parent_execution_uuid' => null,
        ];
    }

    /**
     * Indicate that the execution is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'started_at' => null,
            'completed_at' => null,
            'processed_records' => 0,
            'failed_records' => 0,
        ]);
    }

    /**
     * Indicate that the execution is processing.
     */
    public function processing(): static
    {
        return $this->state(function (array $attributes) {
            $totalRecords = $attributes['total_records'];
            $processedRecords = $this->faker->numberBetween(1, $totalRecords - 1);

            return [
                'status' => 'processing',
                'started_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
                'completed_at' => null,
                'processed_records' => $processedRecords,
                'failed_records' => $this->faker->numberBetween(0, (int)($processedRecords * 0.1)),
            ];
        });
    }

    /**
     * Indicate that the execution is completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $totalRecords = $attributes['total_records'];
            $startedAt = $this->faker->dateTimeBetween('-2 hours', '-1 hour');
            $completedAt = $this->faker->dateTimeBetween($startedAt, 'now');

            return [
                'status' => 'completed',
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'processed_records' => $totalRecords,
                'failed_records' => 0,
            ];
        });
    }

    /**
     * Indicate that the execution failed.
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $totalRecords = $attributes['total_records'];
            $processedRecords = $this->faker->numberBetween(0, (int)($totalRecords * 0.5));
            $startedAt = $this->faker->dateTimeBetween('-2 hours', '-1 hour');
            $completedAt = $this->faker->dateTimeBetween($startedAt, 'now');

            return [
                'status' => 'failed',
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'processed_records' => $processedRecords,
                'failed_records' => $this->faker->numberBetween(1, $processedRecords),
                'error_details' => [
                    'message' => $this->faker->sentence(),
                    'exception' => 'Exception',
                    'file' => '/path/to/file.php',
                    'line' => $this->faker->numberBetween(1, 500),
                ],
            ];
        });
    }

    /**
     * Indicate that the execution was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(function (array $attributes) {
            $totalRecords = $attributes['total_records'];
            $processedRecords = $this->faker->numberBetween(0, (int)($totalRecords * 0.7));
            $startedAt = $this->faker->dateTimeBetween('-2 hours', '-1 hour');
            $completedAt = $this->faker->dateTimeBetween($startedAt, 'now');

            return [
                'status' => 'cancelled',
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'processed_records' => $processedRecords,
                'failed_records' => 0,
            ];
        });
    }

    /**
     * Indicate that the execution has undo enabled.
     */
    public function withUndo(int $expiryDays = 7): static
    {
        return $this->state(fn (array $attributes) => [
            'can_undo' => true,
            'undo_expires_at' => now()->addDays($expiryDays),
        ]);
    }

    /**
     * Indicate that the execution is a dry run.
     */
    public function dryRun(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_dry_run' => true,
            'status' => 'completed',
            'dry_run_results' => [
                'affected_count' => $attributes['total_records'],
                'sample_records' => [
                    ['id' => 1, 'name' => 'Sample 1'],
                    ['id' => 2, 'name' => 'Sample 2'],
                    ['id' => 3, 'name' => 'Sample 3'],
                ],
            ],
        ]);
    }

    /**
     * Indicate that the execution is scheduled.
     */
    public function scheduled(?\DateTimeInterface $datetime = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'scheduled_for' => $datetime ?? $this->faker->dateTimeBetween('now', '+1 week'),
            'scheduled_timezone' => $this->faker->timezone(),
        ]);
    }

    /**
     * Set a specific action type.
     */
    public function action(string $actionName): static
    {
        return $this->state(fn (array $attributes) => [
            'action_name' => $actionName,
        ]);
    }

    /**
     * Set a specific model type.
     */
    public function forModel(string $modelClass): static
    {
        return $this->state(fn (array $attributes) => [
            'model_type' => $modelClass,
        ]);
    }

    /**
     * Set filters.
     */
    public function withFilters(array $filters): static
    {
        return $this->state(fn (array $attributes) => [
            'filters' => $filters,
        ]);
    }

    /**
     * Set parameters.
     */
    public function withParameters(array $parameters): static
    {
        return $this->state(fn (array $attributes) => [
            'parameters' => $parameters,
        ]);
    }

    /**
     * Set user.
     */
    public function forUser(int $userId, string $userType = 'App\\Models\\User'): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
            'user_type' => $userType,
        ]);
    }
}
