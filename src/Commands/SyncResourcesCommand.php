<?php

namespace MahmoudSehsah\FilamentResourceManager\Commands;

use Filament\Facades\Filament;
use Illuminate\Console\Command;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceSynchroniser;
use Throwable;

class SyncResourcesCommand extends Command
{
    protected $signature = 'filament-resource-manager:sync
                            {--panel= : The panel to sync. Defaults to every registered panel.}';

    protected $description = 'Create settings rows for Filament resources that do not have one yet.';

    public function handle(): int
    {
        $panels = $this->panels();

        if ($panels === []) {
            $this->components->error('No Filament panels are registered.');

            return self::FAILURE;
        }

        foreach ($panels as $panel) {
            try {
                // Resource discovery reads whichever panel Filament considers
                // current, so point it at the one being synced.
                if (method_exists(Filament::getFacadeRoot(), 'setCurrentPanel')) {
                    Filament::setCurrentPanel($panel);
                }

                $result = ResourceSynchroniser::sync($panel);

                $this->components->info(sprintf(
                    'Panel [%s]: %d created, %d updated, %d orphaned.',
                    $panel->getId(),
                    $result['created'],
                    $result['updated'],
                    $result['orphaned'],
                ));
            } catch (Throwable $exception) {
                $this->components->error(sprintf(
                    'Panel [%s] failed: %s',
                    $panel->getId(),
                    $exception->getMessage(),
                ));

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, \Filament\Panel>
     */
    protected function panels(): array
    {
        if (! method_exists(Filament::getFacadeRoot(), 'getPanels')) {
            return [];
        }

        $panels = Filament::getPanels();

        if ($id = $this->option('panel')) {
            return isset($panels[$id]) ? [$panels[$id]] : [];
        }

        return array_values($panels);
    }
}
