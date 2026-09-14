<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use MahmoudSehsah\FilamentResourceManager\Support\DynamicBadgeResolver;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileManager;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceDiscovery;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceSynchroniser;
use Throwable;

trait ManagesNavigationStudio
{
    public ?int $profileId = null;

    /** @var array<int, array<string, mixed>> */
    public array $studioItems = [];

    public string $newProfileName = '';

    public function mountManagesNavigationStudio(): void
    {
        $panel = ResourceDiscovery::panel();

        if ($panel === null) {
            return;
        }

        ResourceSynchroniser::sync($panel);
        $profile = ProfileManager::ensureDefault($panel->getId());
        $this->profileId = $profile?->getKey();
        $this->loadStudioItems();
    }

    public function updatedProfileId(): void
    {
        $this->loadStudioItems();
    }

    public function createProfile(): void
    {
        $panelId = ResourceDiscovery::panelId();
        $name = trim($this->newProfileName);

        if ($panelId === null || $name === '' || mb_strlen($name) > 255) {
            $this->notifyError(__('filament-resource-manager::manager.studio.invalid_profile_name'));

            return;
        }

        try {
            $profile = ProfileManager::create($panelId, $name, $this->profile());
            $this->profileId = (int) $profile->getKey();
            $this->newProfileName = '';
            $this->loadStudioItems();
            $this->notifySuccess(__('filament-resource-manager::manager.notifications.profile_created'));
        } catch (Throwable $exception) {
            $this->notifyError($exception->getMessage());
        }
    }

    /** @param array<int, array<string, mixed>> $layout */
    public function saveLayout(array $layout): void
    {
        $profile = $this->profile();

        if (! $profile instanceof Model) {
            return;
        }

        try {
            ProfileManager::saveLayout($profile, $layout);
            $this->loadStudioItems();
            $this->notifySuccess(__('filament-resource-manager::manager.notifications.draft_saved'));
        } catch (Throwable $exception) {
            $this->notifyError($exception->getMessage());
        }
    }

    public function publishProfile(): void
    {
        $profile = $this->profile();

        if (! $profile instanceof Model) {
            return;
        }

        try {
            $version = ProfileManager::publish($profile);
            $this->notifySuccess(__('filament-resource-manager::manager.notifications.profile_published', [
                'version' => $version->version,
            ]));
        } catch (Throwable $exception) {
            $this->notifyError($exception->getMessage());
        }
    }

    public function rollbackTo(int $versionId): void
    {
        $profile = $this->profile();

        if (! $profile instanceof Model) {
            return;
        }

        try {
            $version = ProfileManager::rollback($profile, $versionId);
            $this->loadStudioItems();
            $this->notifySuccess(__('filament-resource-manager::manager.notifications.profile_rolled_back', [
                'version' => $version->version,
            ]));
        } catch (Throwable $exception) {
            $this->notifyError($exception->getMessage());
        }
    }

    public function deleteVersion(int $versionId): void
    {
        $profile = $this->profile();

        if (! $profile instanceof Model) {
            return;
        }

        try {
            if (! ProfileManager::deleteVersion($profile, $versionId)) {
                $this->notifyError(__('filament-resource-manager::manager.studio.current_version_protected'));

                return;
            }

            $this->notifySuccess(__('filament-resource-manager::manager.notifications.version_deleted'));
        } catch (Throwable $exception) {
            $this->notifyError($exception->getMessage());
        }
    }

    public function deleteOldVersions(): void
    {
        $profile = $this->profile();

        if (! $profile instanceof Model) {
            return;
        }

        try {
            $count = ProfileManager::deleteOldVersions($profile);
            $this->notifySuccess(__('filament-resource-manager::manager.notifications.old_versions_deleted', [
                'count' => $count,
            ]));
        } catch (Throwable $exception) {
            $this->notifyError($exception->getMessage());
        }
    }

    protected function getViewData(): array
    {
        $panelId = ResourceDiscovery::panelId();
        $profile = $this->profile();
        $profiles = collect();
        $versions = collect();
        $versionCount = 0;

        if ($panelId !== null) {
            try {
                $model = ProfileManager::profileModel();
                $profiles = $model::query()->where('panel_id', $panelId)->orderByDesc('is_default')->orderBy('name')->get();
                $versionCount = $profile?->versions()->count() ?? 0;
                $versions = $profile?->versions()->latest('version')->limit(20)->get() ?? collect();
            } catch (Throwable) {
                // The view shows its migration hint when profiles are unavailable.
            }
        }

        $groups = collect($this->studioItems)
            ->pluck('navigation_group')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $group): array => ['key' => $group, 'label' => $group])
            ->prepend([
                'key' => '',
                'label' => __('filament-resource-manager::manager.studio.ungrouped'),
            ])
            ->values()
            ->all();

        return compact('profile', 'profiles', 'versions', 'versionCount', 'groups');
    }

    protected function profile(): ?Model
    {
        $panelId = ResourceDiscovery::panelId();

        if ($panelId === null || $this->profileId === null) {
            return null;
        }

        try {
            $model = ProfileManager::profileModel();

            return $model::query()
                ->where('panel_id', $panelId)
                ->whereKey($this->profileId)
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    protected function loadStudioItems(): void
    {
        $profile = $this->profile();

        if (! $profile instanceof Model) {
            $this->studioItems = [];

            return;
        }

        $this->studioItems = $profile->items()
            ->where('is_orphaned', false)
            ->orderBy('sort')
            ->get()
            ->map(fn ($item): array => [
                'resource_class' => $item->resource_class,
                'label' => $item->effective_label ?: class_basename($item->resource_class),
                'icon' => $item->effective_icon,
                'navigation_group' => $item->effective_navigation_group,
                'parent_resource_class' => $item->parent_resource_class,
                'sort' => $item->sort,
                'is_visible' => (bool) $item->is_visible,
                'badge' => DynamicBadgeResolver::resolve([
                    'badge' => $item->badge,
                    'badge_type' => $item->badge_type,
                    'badge_model' => $item->badge_model,
                    'badge_conditions' => $item->badge_conditions,
                ]),
            ])
            ->values()
            ->all();
    }

    protected function notifySuccess(string $message): void
    {
        Notification::make()->title($message)->success()->send();
    }

    protected function notifyError(string $message): void
    {
        Notification::make()->title($message)->danger()->send();
    }
}
