<?php

namespace DhruvilNagar\ActionEngine\Console\Commands;

use DhruvilNagar\ActionEngine\Actions\ActionRegistry;
use Illuminate\Console\Command;

class ListActionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'action-engine:list';

    /**
     * The console command description.
     */
    protected $description = 'List all registered bulk actions';

    /**
     * Execute the console command.
     */
    public function handle(ActionRegistry $registry): int
    {
        $actions = $registry->allWithMetadata();

        if (empty($actions)) {
            $this->warn('No actions registered.');
            return Command::SUCCESS;
        }

        $this->info('Registered Bulk Actions:');
        $this->info('');

        $rows = [];

        foreach ($actions as $name => $metadata) {
            $rows[] = [
                $name,
                $metadata['label'] ?? $name,
                $metadata['supports_undo'] ? '✓' : '✗',
                $metadata['description'] ?? '-',
            ];
        }

        $this->table(
            ['Name', 'Label', 'Undo Support', 'Description'],
            $rows
        );

        return Command::SUCCESS;
    }
}
