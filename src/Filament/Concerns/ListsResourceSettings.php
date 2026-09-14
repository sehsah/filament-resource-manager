<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use MahmoudSehsah\FilamentResourceManager\Support\OverrideRepository;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceDiscovery;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceSynchroniser;

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
                    static::getResource()::getEloquentQuery()->update([
                        'label' => null,
                        'icon' => null,
                        'active_icon' => null,
                        'navigation_group' => null,
                        'navigation_parent_item' => null,
                        'parent_resource_class' => null,
                        'badge' => null,
                        'badge_type' => 'static',
                        'badge_model' => null,
                        'badge_conditions' => null,
                        'badge_color' => null,
                        'badge_tooltip' => null,
                        'is_visible' => true,
                    ]);

                    OverrideRepository::flush();

                    Notification::make()
                        ->title(__('filament-resource-manager::manager.notifications.reset'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
