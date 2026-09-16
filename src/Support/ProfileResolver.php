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
 * Role-targeted published profiles take precedence for matching users. All
 * other users receive the unrestricted panel default when one is published.
 */
class ProfileResolver
{
    /** @var array<string, Model|null> */
    protected static array $memo = [];

    public static function resolve(?Panel $panel = null, mixed $user = null): ?Model
    {
        if (! config('filament-resource-manager.profiles.enabled', true)) {
            return null;
        }

        $panel ??= ResourceDiscovery::panel();

        if (! $panel instanceof Panel || ! static::tableExists()) {
            return null;
        }

        $panelId = $panel->getId();
        $user ??= auth()->user();
        $userRoles = $user ? AccessResolver::getUserRoles($user) : [];
        sort($userRoles);
        $roleKey = $userRoles !== [] ? implode(',', $userRoles) : '__guest__';
        $memoKey = $panelId.':'.$roleKey;

        if (array_key_exists($memoKey, static::$memo)) {
            return static::$memo[$memoKey];
        }

        try {
            $profileModel = static::profileModel();

            $profiles = $profileModel::query()
                ->where('panel_id', $panelId)
                ->whereNotNull('published_version_id')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->get();

            if ($userRoles !== [] && Schema::hasColumn((new $profileModel)->getTable(), 'roles')) {
                $matching = $profiles->first(fn (Model $candidate): bool => array_intersect(
                    (array) ($candidate->roles ?? []),
                    $userRoles,
                ) !== []);

                if ($matching instanceof Model) {
                    return static::$memo[$memoKey] = $matching;
                }
            }

            // Never expose a targeted profile to guests or unrelated roles,
            // even if older publishes accidentally marked it as the default.
            $unrestricted = $profiles->filter(fn (Model $candidate): bool => (array) ($candidate->roles ?? []) === []);

            return static::$memo[$memoKey] = $unrestricted->first(fn (Model $candidate): bool => (bool) $candidate->is_default)
                ?? $unrestricted->first();
        } catch (Throwable) {
            return static::$memo[$memoKey] = null;
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
