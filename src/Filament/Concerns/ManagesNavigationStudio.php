<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use MahmoudSehsah\FilamentResourceManager\Support\AccessResolver;
use MahmoudSehsah\FilamentResourceManager\Support\DynamicBadgeResolver;
use MahmoudSehsah\FilamentResourceManager\Support\NavigationIcon;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileComparison;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileManager;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileTransfer;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceDiscovery;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceSynchroniser;
use MahmoudSehsah\FilamentResourceManager\Support\RolePreview;
use Throwable;

trait ManagesNavigationStudio
{
    public ?int $profileId = null;

    /** @var array<int, array<string, mixed>> */
    public array $studioItems = [];

    public string $newProfileName = '';

    /** @var array<int, string> */
    public array $profileRoles = [];

    public string $importJson = '';

    public string $importName = '';

    #[Locked]
    public string $reviewedImportHash = '';

    public bool $previewEnabled = false;

    public array $previewRoles = [];

    public array $previewPermissions = [];

    public ?int $compareFrom = null;

    public ?int $compareTo = null;

    public function exportProfile(): mixed
    {
        $profile = $this->profile();
        if (! $profile) {
            return null;
        }
        $json = ProfileTransfer::export($profile);

        return response()->streamDownload(fn () => print ($json), 'navigation-profile-'.$profile->getKey().'.json', ['Content-Type' => 'application/json']);
    }

    public function reviewImport(): void
    {
        $this->reviewedImportHash = '';
        try {
            $panel = ResourceDiscovery::panel();
            if (! $panel || ! $this->profile()) {
                return;
            }
            $data = ProfileTransfer::prepare($this->importJson, $panel);
            if (trim($this->importName) === '') {
                $this->importName = $data['name'];
            }
            $this->reviewedImportHash = hash('sha256', $this->importJson);
        } catch (Throwable $exception) {
            $this->notifyError($exception->getMessage());
        }
    }

    public function importProfile(): void
    {
        try {
            $panel = ResourceDiscovery::panel();
            if (! $panel || ! $this->profile()) {
                return;
            }
            if ($this->reviewedImportHash === '' || ! hash_equals($this->reviewedImportHash, hash('sha256', $this->importJson))) {
                $this->notifyError(__('filament-resource-manager::manager.transfer.review_required'));

                return;
            }
            $profile = ProfileTransfer::import($this->importJson, $panel, $this->importName);
            $this->profileId = (int) $profile->getKey();
            $this->importJson = '';
            $this->importName = '';
            $this->reviewedImportHash = '';
            $this->updatedProfileId();
            $this->notifySuccess(__('filament-resource-manager::manager.transfer.imported'));
        } catch (Throwable $exception) {
            $this->notifyError($exception->getMessage());
        }
    }

    public function mountManagesNavigationStudio(): void
    {
        $panel = ResourceDiscovery::panel();

        if ($panel === null) {
            return;
        }

        ResourceSynchroniser::sync($panel);
        $profile = ProfileManager::ensureDefault($panel->getId());
        $this->profileId = $profile?->getKey();
        $this->compareFrom = $profile?->published_version_id;
        $this->loadStudioItems();
    }

    public function updatedProfileId(): void
    {
        $this->compareFrom = $this->profile()?->published_version_id;
        $this->compareTo = null;
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
            $this->updatedProfileId();
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
            $availableRoles = array_map('strval', array_keys(AccessResolver::getAvailableRoles()));
            $knownRoles = array_unique([...$availableRoles, ...(array) $profile->roles]);

            if (collect($this->profileRoles)->contains(
                fn (mixed $role): bool => ! is_string($role) || ! in_array($role, $knownRoles, true),
            )) {
                $this->notifyError(__('filament-resource-manager::manager.studio.invalid_profile_roles'));

                return;
            }

            $version = ProfileManager::publish($profile, roles: $this->profileRoles);
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
        $availableRoles = AccessResolver::getAvailableRoles();

        foreach ((array) $profile?->roles as $role) {
            $availableRoles[$role] ??= $role;
        }

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

        $importReview = null;
        if ($this->reviewedImportHash !== '' && hash_equals($this->reviewedImportHash, hash('sha256', $this->importJson))) {
            try {
                $importReview = ProfileTransfer::prepare($this->importJson, ResourceDiscovery::panel());
            } catch (Throwable) {
                $this->reviewedImportHash = '';
            }
        }
        $comparison = [];
        $comparisonError = null;
        $comparisonVersions = $profile?->versions()->latest('version')->get(['id', 'version']) ?? collect();
        if ($profile && ($this->compareFrom !== null || $this->compareTo !== null)) {
            try {
                $comparison = ProfileComparison::compare($profile, $this->compareFrom, $this->compareTo);
            } catch (Throwable) {
                $comparisonError = __('filament-resource-manager::manager.comparison.unavailable');
            }
        }
        $availablePermissions = AccessResolver::getAvailablePermissions();
        $effectivePreviewPermissions = array_values(array_unique([...RolePreview::permissions($this->previewRoles), ...$this->previewPermissions]));
        $previewReasons = $this->previewEnabled && $profile
            ? RolePreview::reasons(ProfileManager::snapshot($profile), $this->previewRoles, $effectivePreviewPermissions, (array) $profile->roles)
            : [];
        $studioKey = hash('sha256', json_encode([$this->studioItems, $previewReasons, $this->previewEnabled]));

        return compact('profile', 'profiles', 'versions', 'versionCount', 'groups', 'availableRoles', 'importReview', 'comparison', 'comparisonError', 'comparisonVersions', 'availablePermissions', 'effectivePreviewPermissions', 'previewReasons', 'studioKey');
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
            $this->profileRoles = [];

            return;
        }

        $this->profileRoles = (array) ($profile->roles ?? []);
        $this->studioItems = $profile->items()
            ->where('is_orphaned', false)
            ->orderBy('sort')
            ->get()
            ->map(fn ($item): array => [
                'resource_class' => $item->resource_class,
                'label' => $item->effective_label ?: class_basename($item->resource_class),
                'icon' => $item->effective_icon,
                'icon_html' => NavigationIcon::previewHtml($item, 'icon', $item->default_icon),
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
