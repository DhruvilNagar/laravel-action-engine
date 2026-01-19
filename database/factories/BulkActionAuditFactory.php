<?php

namespace DhruvilNagar\ActionEngine\Database\Factories;

use DhruvilNagar\ActionEngine\Models\BulkActionUndo;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\DhruvilNagar\ActionEngine\Models\BulkActionUndo>
 */
class BulkActionUndoFactory extends Factory
{
    protected $model = BulkActionUndo::class;

    public function definition(): array
    {
        return [
            'bulk_action_execution_id' => BulkActionExecution::factory(),
            'model_type' => 'App\\Models\\User',
            'model_id' => $this->faker->numberBetween(1, 1000),
            'original_data' => [
                'id' => $this->faker->numberBetween(1, 1000),
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
                'status' => $this->faker->randomElement(['active', 'inactive', 'suspended']),
            ],
            'changes' => [
                'status' => [
                    'old' => 'active',
                    'new' => 'inactive',
                ],
            ],
            'undo_action_type' => $this->faker->randomElement(['restore', 'delete', 'update']),
            'undone' => false,
            'undone_at' => null,
        ];
    }

    /**
     * Indicate that the record has been undone.
     */
    public function undone(): static
    {
        return $this->state(fn (array $attributes) => [
            'undone' => true,
            'undone_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
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
     * Set the model type.
     */
    public function forModel(string $modelClass, int $modelId): static
    {
        return $this->state(fn (array $attributes) => [
            'model_type' => $modelClass,
            'model_id' => $modelId,
        ]);
    }

    /**
     * Set undo action type to restore.
     */
    public function restore(): static
    {
        return $this->state(fn (array $attributes) => [
            'undo_action_type' => 'restore',
            'changes' => ['deleted_at' => ['old' => null, 'new' => now()->toDateTimeString()]],
        ]);
    }

    /**
     * Set undo action type to delete.
     */
    public function delete(): static
    {
        return $this->state(fn (array $attributes) => [
            'undo_action_type' => 'delete',
            'changes' => null,
        ]);
    }

    /**
     * Set undo action type to update.
     */
    public function update(array $changes = []): static
    {
        return $this->state(fn (array $attributes) => [
            'undo_action_type' => 'update',
            'changes' => !empty($changes) ? $changes : $attributes['changes'],
        ]);
    }
}
