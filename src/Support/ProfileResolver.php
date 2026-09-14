<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use MahmoudSehsah\FilamentResourceManager\Models\NavigationProfile;
use Throwable;

/**
 * Resolves the published profile active for a panel.
 *
 * Profiles are intentionally panel-level. Publishing a profile makes it the
 * panel default, while its previous published versions remain available for
 * rollback.
 */
class ProfileResolver
{
    /** @var array<string, Model|null> */
    protected static array $memo = [];

    public static function resolve(?Panel $panel = null): ?Model
    {
        if (! config('filament-resource-manager.profiles.enabled', true)) {
            return null;
        }

        $panel ??= ResourceDiscovery::panel();

        if (! $panel instanceof Panel || ! static::tableExists()) {
            return null;
        }

        $panelId = $panel->getId();

        if (array_key_exists($panelId, static::$memo)) {
            return static::$memo[$panelId];
        }

        try {
            $profileModel = static::profileModel();

            return static::$memo[$panelId] = $profileModel::query()
                ->where('panel_id', $panelId)
                ->where('is_default', true)
                ->whereNotNull('published_version_id')
                ->first();
        } catch (Throwable) {
            return static::$memo[$panelId] = null;
        }
    }

    public static function flush(): void
    {
        static::$memo = [];
    }

    protected static function tableExists(): bool
    {
        try {
            return Schema::hasTable(config(
                'filament-resource-manager.profiles.tables.profiles',
                'filament_navigation_profiles',
            ));
        } catch (Throwable) {
            return false;
        }
    }

    /** @return class-string<Model> */
    protected static function profileModel(): string
    {
        return config('filament-resource-manager.profiles.models.profile', NavigationProfile::class);
    }
}
