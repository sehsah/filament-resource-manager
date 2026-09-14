<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use MahmoudSehsah\FilamentResourceManager\Support\OverrideRepository;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileManager;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceDiscovery;
use MahmoudSehsah\FilamentResourceManager\Support\TableColumns;

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
     * Once a navigation profile has been published, the published snapshot is
     * what renders - so what this page just saved has gone into that profile's
     * draft, and reaches the sidebar on the next publish. Saying so beats
     * letting an administrator wonder why nothing moved.
     */
    protected function getSavedNotification(): ?Notification
    {
        $profile = ProfileManager::governingProfile(ResourceDiscovery::panelId());

        if ($profile === null) {
            return parent::getSavedNotification();
        }

        return Notification::make()
            ->warning()
            ->title(__('filament-resource-manager::manager.notifications.saved_to_draft'))
            ->body(__('filament-resource-manager::manager.notifications.saved_to_draft_body', [
                'profile' => $profile->name,
            ]))
            ->persistent();
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
                    $record = $this->getRecord();

                    $record->forceFill(TableColumns::only($record->getTable(), [
                        'label' => null,
                        'icon' => null,
                        'active_icon' => null,
                        'navigation_group' => null,
                        'navigation_group_overridden' => false,
                        'navigation_parent_item' => null,
                        'parent_resource_class' => null,
                        'badge' => null,
                        'badge_type' => 'static',
                        'badge_model' => null,
                        'badge_conditions' => null,
                        'badge_color' => null,
                        'badge_tooltip' => null,
                        'is_visible' => true,
                    ]))->save();

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
