<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use MahmoudSehsah\FilamentResourceManager\Support\OverrideRepository;

/**
 * Shared body for the manager's edit page. Filament\Actions\Action and
 * EditRecord::getHeaderActions() are the same in v3, v4 and v5.
 */
trait EditsResourceSetting
{
    public function getTitle(): string
    {
        return $this->getRecord()->effective_label
            ?? __('filament-resource-manager::manager.model_label');
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->resource_class;
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetThisResource')
                ->label(__('filament-resource-manager::manager.actions.reset_one'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('filament-resource-manager::manager.actions.reset_one_confirm'))
                ->action(function (): void {
                    $this->getRecord()->forceFill([
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
                    ])->save();

                    OverrideRepository::flush();

                    $this->fillForm();

                    Notification::make()
                        ->title(__('filament-resource-manager::manager.notifications.reset'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
