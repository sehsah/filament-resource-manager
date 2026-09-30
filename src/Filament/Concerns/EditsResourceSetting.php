<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileManager;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceDiscovery;
use MahmoudSehsah\FilamentResourceManager\Support\SettingsReset;

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

    /**
     * Publish now rather than when the request ends, so the notification
     * below can truthfully say the change is live.
     */
    protected function afterSave(): void
    {
        ProfileManager::publishPending();
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

        if ($profile === null || ProfileManager::autoPublishEnabled()) {
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
                    SettingsReset::one($this->getRecord());
                    ProfileManager::publishPending();

                    $this->fillForm();

                    Notification::make()
                        ->title(__('filament-resource-manager::manager.notifications.reset'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
