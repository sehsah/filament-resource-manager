<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceDiscovery;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceSynchroniser;
use MahmoudSehsah\FilamentResourceManager\Support\SettingsReset;

/**
 * Shared body for the manager's list page. Filament\Actions\Action and
 * ListRecords::getHeaderActions() are the same in v3, v4 and v5.
 */
trait ListsResourceSettings
{
    public function mount(): void
    {
        parent::mount();

        if (config('filament-resource-manager.auto_sync', true)) {
            ResourceSynchroniser::sync(ResourceDiscovery::panel());
        }
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('studio')
                ->label(__('filament-resource-manager::manager.actions.studio'))
                ->icon('heroicon-o-swatch')
                ->color('primary')
                ->url(static::getResource()::getUrl('studio')),

            Action::make('sync')
                ->label(__('filament-resource-manager::manager.actions.sync'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $result = ResourceSynchroniser::sync(ResourceDiscovery::panel());

                    Notification::make()
                        ->title(__('filament-resource-manager::manager.notifications.synced'))
                        ->body(__('filament-resource-manager::manager.notifications.synced_body', $result))
                        ->success()
                        ->send();
                }),

            Action::make('reset')
                ->label(__('filament-resource-manager::manager.actions.reset'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('filament-resource-manager::manager.actions.reset_confirm'))
                ->action(function (): void {
                    SettingsReset::all(static::getResource()::getEloquentQuery());

                    Notification::make()
                        ->title(__('filament-resource-manager::manager.notifications.reset'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
