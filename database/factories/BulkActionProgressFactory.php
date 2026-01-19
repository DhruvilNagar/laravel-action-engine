<?php

namespace DhruvilNagar\ActionEngine\Database\Factories;

use DhruvilNagar\ActionEngine\Models\BulkActionProgress;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\DhruvilNagar\ActionEngine\Models\BulkActionProgress>
 */
class BulkActionProgressFactory extends Factory
{
    protected $model = BulkActionProgress::class;

    public function definition(): array
    {
        $batchSize = $this->faker->numberBetween(100, 1000);
        $processedCount = $this->faker->numberBetween(0, $batchSize);

        return [
            'bulk_action_execution_id' => BulkActionExecution::factory(),
            'batch_number' => $this->faker->numberBetween(1, 10),
            'batch_size' => $batchSize,
            'processed_count' => $processedCount,
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']),
            'affected_ids' => $this->faker->randomElements(range(1, 100), $this->faker->numberBetween(5, 20)),
            'error_message' => null,
            'started_at' => $this->faker->optional()->dateTime(),
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the batch is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'processed_count' => 0,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the batch is processing.
     */
    public function processing(): static
    {
        return $this->state(function (array $attributes) {
            $batchSize = $attributes['batch_size'];
            $processedCount = $this->faker->numberBetween(1, $batchSize - 1);

            return [
                'status' => 'processing',
                'processed_count' => $processedCount,
                'started_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
                'completed_at' => null,
            ];
        });
    }

    /**
     * Indicate that the batch is completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $batchSize = $attributes['batch_size'];
            $startedAt = $this->faker->dateTimeBetween('-2 hours', '-1 hour');
            $completedAt = $this->faker->dateTimeBetween($startedAt, 'now');

            return [
                'status' => 'completed',
                'processed_count' => $batchSize,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
            ];
        });
    }

    /**
     * Indicate that the batch failed.
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $batchSize = $attributes['batch_size'];
            $processedCount = $this->faker->numberBetween(0, (int)($batchSize * 0.5));
            $startedAt = $this->faker->dateTimeBetween('-2 hours', '-1 hour');
            $completedAt = $this->faker->dateTimeBetween($startedAt, 'now');

            return [
                'status' => 'failed',
                'processed_count' => $processedCount,
                'error_message' => $this->faker->sentence(),
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
            ];
        });
    }

    /**
     * Set the execution.
     */
    public function forExecution(BulkActionExecution $execution): static
    {
        return $this->state(fn (array $attributes) => [
            'bulk_action_execution_id' => $execution->id,
        ]);
    }

    /**
     * Set the batch number.
     */
    public function batchNumber(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'batch_number' => $number,
        ]);
    }
}
