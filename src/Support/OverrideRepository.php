<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use MahmoudSehsah\FilamentResourceManager\Models\ResourceSetting;
use Throwable;

/**
 * Loads the navigation overrides for a panel.
 *
 * Navigation is rebuilt on every page render, so the overrides are cached and
 * the cache is cleared whenever a setting is saved.
 */
class OverrideRepository
{
    /**
     * The columns that can override a navigation item, in the order they are
     * applied. Keep in step with ManagedNavigationManager::applyOverride().
     *
     * @var array<int, string>
     */
    public const ATTRIBUTES = [
        'label',
        'icon',
        'active_icon',
        'navigation_group',
        'navigation_parent_item',
        'parent_resource_class',
        'sort',
        'badge',
        'badge_type',
        'badge_model',
        'badge_conditions',
        'badge_color',
        'badge_tooltip',
        'is_visible',
    ];

    /** @var array<string, array<string, array<string, mixed>>> */
    protected static array $memo = [];

    /**
     * While true, flush() only clears the per-request memo and leaves the cache
     * entry alone. Used to collapse a burst of writes into one flush.
     */
    protected static bool $flushSuspended = false;

    /**
     * @return array<string, array<string, mixed>> keyed by resource class
     */
    public static function forPanel(?string $panelId): array
    {
        $profile = ProfileResolver::resolve(ResourceDiscovery::panel());
        $memoKey = implode('.', [
            $panelId ?? '__default__',
            $profile?->getKey() ?? 'legacy',
            $profile?->published_version_id ?? 'current',
        ]);

        if (array_key_exists($memoKey, static::$memo)) {
            return static::$memo[$memoKey];
        }

        return static::$memo[$memoKey] = static::load($panelId, $profile);
    }

    /**
     * Runs a callback with cache flushing suspended, then leaves it to the
     * caller to flush once. A bulk write - the synchroniser touching every row
     * on a panel - would otherwise flush on each individual save.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutFlushing(callable $callback): mixed
    {
        $previous = static::$flushSuspended;
        static::$flushSuspended = true;

        try {
            return $callback();
        } finally {
            static::$flushSuspended = $previous;
        }
    }

    public static function flush(): void
    {
        static::$memo = [];

        if (static::$flushSuspended) {
            return;
        }

        try {
            static::cache()->forget(static::cacheKey(null));

            foreach (static::knownPanelIds() as $panelId) {
                static::cache()->forget(static::cacheKey($panelId));
            }
        } catch (Throwable) {
            // A missing or misconfigured cache store must not break saving.
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function load(?string $panelId, ?Model $profile = null): array
    {
        if (! static::tableExists()) {
            return [];
        }

        $resolve = fn (): array => $profile instanceof Model
            ? static::queryProfile($profile)
            : static::query($panelId);

        if (! config('filament-resource-manager.cache.enabled', true)) {
            return $resolve();
        }

        try {
            return static::cache()->remember(
                static::cacheKey(
                    $panelId,
                    $profile?->getKey(),
                    $profile?->published_version_id,
                ),
                (int) config('filament-resource-manager.cache.ttl', 3600),
                $resolve,
            );
        } catch (Throwable) {
            return $resolve();
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function query(?string $panelId): array
    {
        try {
            /** @var class-string<Model> $model */
            $model = config('filament-resource-manager.model', ResourceSetting::class);

            return $model::query()
                ->where('panel_id', $panelId)
                ->where('is_orphaned', false)
                ->get(array_merge(['resource_class'], static::ATTRIBUTES))
                ->keyBy('resource_class')
                ->map(function ($row): array {
                    $override = [];

                    foreach (static::ATTRIBUTES as $attribute) {
                        $override[$attribute] = $row->{$attribute};
                    }

                    $override['is_visible'] = (bool) $override['is_visible'];

                    return $override;
                })
                ->all();
        } catch (Throwable) {
            // Before the migration runs, or while the database is unreachable,
            // navigation should simply render untouched.
            return [];
        }
    }

    /** @return array<string, array<string, mixed>> */
    protected static function queryProfile(Model $profile): array
    {
        try {
            $version = $profile->publishedVersion()->first();

            if (! $version instanceof Model) {
                return [];
            }

            $overrides = [];

            foreach ((array) $version->snapshot as $item) {
                $resource = $item['resource_class'] ?? null;

                if (! is_string($resource) || ($item['is_orphaned'] ?? false)) {
                    continue;
                }

                $override = [];

                foreach (static::ATTRIBUTES as $attribute) {
                    $override[$attribute] = $item[$attribute] ?? null;
                }

                $override['navigation_group_overridden'] = (bool) ($item['navigation_group_overridden'] ?? false);
                $override['is_visible'] = (bool) ($item['is_visible'] ?? true);
                $overrides[$resource] = $override;
            }

            return $overrides;
        } catch (Throwable) {
            return [];
        }
    }

    protected static function tableExists(): bool
    {
        try {
            return Schema::hasTable(config('filament-resource-manager.table_name', 'filament_resource_settings'));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    protected static function knownPanelIds(): array
    {
        try {
            /** @var class-string<Model> $model */
            $model = config('filament-resource-manager.model', ResourceSetting::class);

            return $model::query()->distinct()->pluck('panel_id')->filter()->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected static function cacheKey(
        ?string $panelId,
        int|string|null $profileId = null,
        int|string|null $versionId = null,
    ): string {
        $base = (string) config('filament-resource-manager.cache.key', 'filament-resource-manager.overrides');

        $key = $base.'.'.($panelId ?? 'default');

        return $profileId === null
            ? $key
            : $key.'.profile.'.$profileId.'.version.'.($versionId ?? 'none');
    }

    protected static function cache(): CacheRepository
    {
        return Cache::store(config('filament-resource-manager.cache.store'));
    }
}
