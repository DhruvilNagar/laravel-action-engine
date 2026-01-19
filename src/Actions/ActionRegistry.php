<?php

namespace DhruvilNagar\ActionEngine\Actions;

use Closure;
use DhruvilNagar\ActionEngine\Contracts\ActionInterface;
use DhruvilNagar\ActionEngine\Exceptions\InvalidActionException;

/**
 * ActionRegistry
 * 
 * Central registry for managing and resolving bulk action handlers.
 * 
 * Maintains a collection of registered actions with their handlers (closures or class names)
 * and associated metadata such as labels, icons, undo support, and parameters.
 * 
 * Actions can be:
 * - Closure-based: Anonymous functions executed directly
 * - Class-based: Resolved from Laravel's service container
 * 
 * @example
 * $registry->register('archive', ArchiveAction::class, [
 *     'label' => 'Archive Records',
 *     'supports_undo' => true,
 *     'confirmation_required' => true,
 * ]);
 */
class ActionRegistry
{
    /**
     * Map of action names to their handlers (Closure or class string).
     * 
     * @var array<string, Closure|string>
     */
    protected array $actions = [];

    /**
     * Metadata for each registered action including labels, icons, and configuration.
     * 
     * @var array<string, array>
     */
    protected array $metadata = [];

    /**
     * Register a custom action handler with optional metadata.
     *
     * @param string $name Unique action identifier (e.g., 'delete', 'archive')
     * @param Closure|string $handler Action handler (closure or fully qualified class name)
     * @param array $options Metadata options:
     *   - label: Human-readable action name
     *   - supports_undo: Whether action can be undone
     *   - undo_type: Type of undo operation
     *   - description: Action description for UI
     *   - icon: Icon identifier for UI
     *   - color: Color scheme identifier
     *   - confirmation_required: Whether to show confirmation dialog
     *   - confirmation_message: Custom confirmation message
     *   - parameters: Expected parameters schema
     * @return void
     */
    public function register(string $name, Closure|string $handler, array $options = []): void
    {
        $this->actions[$name] = $handler;
        $this->metadata[$name] = array_merge([
            'name' => $name,
            'label' => $options['label'] ?? ucfirst(str_replace(['_', '-'], ' ', $name)),
            'supports_undo' => $options['supports_undo'] ?? false,
            'undo_type' => $options['undo_type'] ?? null,
            'description' => $options['description'] ?? null,
            'icon' => $options['icon'] ?? null,
            'color' => $options['color'] ?? null,
            'confirmation_required' => $options['confirmation_required'] ?? true,
            'confirmation_message' => $options['confirmation_message'] ?? null,
            'parameters' => $options['parameters'] ?? [],
        ], $options);
    }

    /**
     * Retrieve and resolve an action handler by name.
     * 
     * If the handler is a class string, it will be resolved from Laravel's
     * service container allowing for dependency injection.
     *
     * @param string $name The registered action name
     * @return Closure|ActionInterface The resolved action handler
     * @throws InvalidActionException When action is not registered
     */
    public function get(string $name): Closure|ActionInterface
    {
        if (!$this->has($name)) {
            throw new InvalidActionException("Action '{$name}' is not registered.");
        }

        $handler = $this->actions[$name];

        // If it's a class string, resolve it from the container
        if (is_string($handler)) {
            return app($handler);
        }

        return $handler;
    }

    /**
     * Check if an action is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->actions[$name]);
    }

    /**
     * Get all registered actions.
     */
    public function all(): array
    {
        return array_keys($this->actions);
    }

    /**
     * Get metadata for an action.
     */
    public function getMetadata(string $name): array
    {
        return $this->metadata[$name] ?? [];
    }

    /**
     * Get all actions with their metadata.
     */
    public function allWithMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Unregister an action.
     */
    public function unregister(string $name): void
    {
        unset($this->actions[$name], $this->metadata[$name]);
    }

    /**
     * Register multiple actions at once.
     */
    public function registerMany(array $actions): void
    {
        foreach ($actions as $name => $action) {
            if (is_array($action) && isset($action['handler'])) {
                // Array format with metadata
                $handler = $action['handler'];
                unset($action['handler']);
                $this->register($name, $handler, $action);
            } else {
                // Simple closure format
                $this->register($name, $action);
            }
        }
    }

    /**
     * Get actions that support undo.
     */
    public function getUndoableActions(): array
    {
        return array_filter($this->metadata, fn($meta) => $meta['supports_undo'] ?? false);
    }

    /**
     * Check if an action supports undo.
     */
    public function supportsUndo(string $name): bool
    {
        return $this->metadata[$name]['supports_undo'] ?? false;
    }

    /**
     * Get the undo type for an action.
     */
    public function getUndoType(string $name): ?string
    {
        return $this->metadata[$name]['undo_type'] ?? null;
    }

    /**
     * Get an action's label for display.
     */
    public function getLabel(string $name): string
    {
        return $this->metadata[$name]['label'] ?? ucfirst($name);
    }

    /**
     * Check if an action requires confirmation.
     */
    public function requiresConfirmation(string $name): bool
    {
        return $this->metadata[$name]['confirmation_required'] ?? true;
    }

    /**
     * Get the confirmation message for an action.
     */
    public function getConfirmationMessage(string $name): ?string
    {
        return $this->metadata[$name]['confirmation_message'] ?? null;
    }
}
