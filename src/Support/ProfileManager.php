<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use MahmoudSehsah\FilamentResourceManager\Models\NavigationProfile;
use MahmoudSehsah\FilamentResourceManager\Models\NavigationProfileItem;
use MahmoudSehsah\FilamentResourceManager\Models\NavigationProfileVersion;
use MahmoudSehsah\FilamentResourceManager\Models\ResourceSetting;
use Throwable;

class ProfileManager
{
    public const ITEM_ATTRIBUTES = [
        'resource_class',
        'label',
        'icon',
        'active_icon',
        'navigation_group',
        'navigation_group_overridden',
        'parent_resource_class',
        'sort',
        'badge',
        'badge_type',
        'badge_model',
        'badge_conditions',
        'badge_color',
        'badge_tooltip',
        'is_visible',
        'default_label',
        'default_icon',
        'default_navigation_group',
        'is_orphaned',
    ];

    public static function ensureDefault(string $panelId): ?Model
    {
        if (! static::tablesExist()) {
            return null;
        }

        try {
            $profileModel = static::profileModel();
            $profile = $profileModel::query()->firstOrCreate(
                ['panel_id' => $panelId, 'slug' => 'default'],
                ['name' => 'Default', 'status' => 'draft', 'is_default' => true],
            );

            $hasDefault = $profileModel::query()
                ->where('panel_id', $panelId)
                ->where('is_default', true)
                ->whereKeyNot($profile->getKey())
                ->exists();

            if (! $hasDefault && ! $profile->is_default) {
                $profile->forceFill(['is_default' => true])->save();
            }

            if (! $profile->items()->exists()) {
                static::seedFromLegacy($profile);
            }

            return $profile->fresh();
        } catch (Throwable) {
            return null;
        }
    }

    public static function create(string $panelId, string $name, ?Model $source = null): Model
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('A profile name is required.');
        }

        return DB::transaction(function () use ($panelId, $name, $source): Model {
            $profileModel = static::profileModel();
            $baseSlug = Str::slug($name) ?: 'profile';
            $slug = $baseSlug;
            $suffix = 2;

            while ($profileModel::query()->where('panel_id', $panelId)->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $profile = $profileModel::query()->create([
                'panel_id' => $panelId,
                'name' => $name,
                'slug' => $slug,
                'status' => 'draft',
                'is_default' => false,
            ]);

            if ($source instanceof Model) {
                foreach ($source->items()->where('is_orphaned', false)->get() as $item) {
                    $profile->items()->create($item->only(static::ITEM_ATTRIBUTES));
                }
            } else {
                static::seedFromLegacy($profile);
            }

            return $profile->fresh();
        });
    }

    /**
     * Keep every profile draft aware of resources added to or removed from a
     * panel. Published navigation remains unchanged until an administrator
     * explicitly publishes the draft.
     *
     * @param  array<int, class-string>  $resources
     */
    public static function syncPanel(string $panelId, array $resources): void
    {
        if (! static::tablesExist()) {
            return;
        }

        $default = static::ensureDefault($panelId);

        if (! $default instanceof Model) {
            return;
        }

        $legacy = static::resourceSettingModel()::query()
            ->where('panel_id', $panelId)
            ->get()
            ->keyBy('resource_class');

        foreach (static::profileModel()::query()->where('panel_id', $panelId)->get() as $profile) {
            $items = $profile->items()->get()->keyBy('resource_class');
            $changed = false;

            foreach ($resources as $resource) {
                $defaults = [
                    'default_label' => ResourceDiscovery::defaultLabel($resource),
                    'default_icon' => ResourceDiscovery::defaultIcon($resource),
                    'default_navigation_group' => ResourceDiscovery::defaultGroup($resource),
                    'is_orphaned' => false,
                ];
                $item = $items->get($resource);

                if ($item === null) {
                    $row = $legacy->get($resource);
                    $profile->items()->create([
                        'resource_class' => $resource,
                        'label' => $row?->label,
                        'icon' => $row?->icon,
                        'active_icon' => $row?->active_icon,
                        'navigation_group' => $row?->navigation_group,
                        'navigation_group_overridden' => (bool) ($row?->navigation_group_overridden)
                            || filled($row?->navigation_group),
                        'parent_resource_class' => $row?->parent_resource_class,
                        'sort' => $row?->sort,
                        'badge' => $row?->badge,
                        'badge_type' => $row?->badge_type ?? 'static',
                        'badge_model' => $row?->badge_model,
                        'badge_conditions' => $row?->badge_conditions,
                        'badge_color' => $row?->badge_color,
                        'badge_tooltip' => $row?->badge_tooltip,
                        'is_visible' => $row?->is_visible ?? true,
                        ...$defaults,
                    ]);
                    $changed = true;

                    continue;
                }

                $item->fill($defaults);

                if ($item->isDirty()) {
                    $item->save();
                    $changed = true;
                }
            }

            $orphaned = $profile->items()
                ->when($resources !== [], fn ($query) => $query->whereNotIn('resource_class', $resources))
                ->where('is_orphaned', false)
                ->update(['is_orphaned' => true]);

            if ($changed || $orphaned > 0) {
                $profile->forceFill(['status' => 'draft'])->save();
            }
        }
    }

    /** @param array<int, array<string, mixed>> $layout */
    public static function saveLayout(Model $profile, array $layout): void
    {
        // Every item save clears the override cache through its model event.
        // Suspending that turns a whole layout's worth of writes into the one
        // flush at the end.
        OverrideRepository::withoutFlushing(fn () => DB::transaction(function () use ($profile, $layout): void {
            $items = $profile->items()->where('is_orphaned', false)->get()->keyBy('resource_class');
            $allowed = $items->keys()->all();
            $parents = [];

            foreach ($layout as $position => $input) {
                $resource = (string) ($input['resource_class'] ?? '');

                if (! in_array($resource, $allowed, true)) {
                    continue;
                }

                $parent = filled($input['parent_resource_class'] ?? null)
                    ? (string) $input['parent_resource_class']
                    : null;

                if ($parent === $resource || ($parent !== null && ! in_array($parent, $allowed, true))) {
                    $parent = null;
                }

                $parents[$resource] = $parent;
                $items[$resource]->forceFill([
                    'navigation_group' => filled($input['navigation_group'] ?? null)
                        ? mb_substr((string) $input['navigation_group'], 0, 255)
                        : null,
                    'navigation_group_overridden' => true,
                    'parent_resource_class' => $parent,
                    'sort' => $position + 1,
                    'is_visible' => (bool) ($input['is_visible'] ?? true),
                ])->save();
            }

            // Break cycles defensively. Filament supports parent items, but a
            // resource can never be its own ancestor.
            foreach ($parents as $resource => $parent) {
                $seen = [$resource => true];
                $cursor = $parent;

                while ($cursor !== null) {
                    if (isset($seen[$cursor])) {
                        $items[$resource]->forceFill(['parent_resource_class' => null])->save();
                        break;
                    }

                    $seen[$cursor] = true;
                    $cursor = $parents[$cursor] ?? null;
                }
            }

            $profile->forceFill(['status' => 'draft'])->save();

            static::mirrorToSettings($profile);
        }));

        OverrideRepository::flush();
    }

    /**
     * Copy the default profile's draft placement back into the resource
     * settings table, so the edit page and the studio show the same thing.
     *
     * Written with query updates rather than model saves: the model's saved
     * event would call syncSetting() and bounce the same values straight back
     * into the items they came from.
     */
    protected static function mirrorToSettings(Model $profile): void
    {
        if (! (bool) $profile->is_default) {
            return;
        }

        $settingModel = static::resourceSettingModel();
        $settingTable = (new $settingModel)->getTable();

        foreach ($profile->items()->where('is_orphaned', false)->get() as $item) {
            $settingModel::query()
                ->where('panel_id', $profile->panel_id)
                ->where('resource_class', $item->resource_class)
                ->update(TableColumns::only($settingTable, [
                    'navigation_group' => $item->navigation_group,
                    'navigation_group_overridden' => (bool) $item->navigation_group_overridden,
                    'parent_resource_class' => $item->parent_resource_class,
                    'sort' => $item->sort,
                    'is_visible' => (bool) $item->is_visible,
                ]));
        }
    }

    /**
     * The attributes an administrator can set on the resource edit page, and
     * that therefore have to reach every profile draft. Keep in step with
     * settingValues() below.
     *
     * @var array<int, string>
     */
    public const SYNCED_SETTING_ATTRIBUTES = [
        'label',
        'icon',
        'active_icon',
        'navigation_group',
        'navigation_group_overridden',
        'parent_resource_class',
        'sort',
        'is_visible',
        'badge',
        'badge_type',
        'badge_model',
        'badge_conditions',
        'badge_color',
        'badge_tooltip',
    ];

    /**
     * The profile that currently governs a panel's navigation, if any.
     *
     * While one exists, the published snapshot is what renders, so edits made
     * on the resource edit page land in the draft and only become visible when
     * the profile is published again.
     */
    public static function governingProfile(?string $panelId = null): ?Model
    {
        $profile = ProfileResolver::resolve();

        if ($profile === null) {
            return null;
        }

        return ($panelId === null || $profile->panel_id === $panelId) ? $profile : null;
    }

    /**
     * Mirror a saved resource setting into every profile draft on its panel.
     *
     * The resource edit page and the Navigation Studio write to two different
     * tables, and the published profile snapshot is what actually renders once
     * a profile has been published. Without this, saving the edit page would
     * appear to do nothing. Published snapshots stay untouched - they are
     * immutable by design - so the edit changes reach navigation on the next
     * publish, and the pages say so.
     *
     * Only the panel's default profile - the one that governs navigation - is
     * mirrored into. Other profiles are alternative layouts and keep their own
     * arrangement until an administrator switches to them.
     */
    public static function syncSetting(Model $setting): void
    {
        try {
            $badgeColumns = ['badge_type', 'badge_model', 'badge_conditions'];

            if (! static::tablesExist() || ! TableColumns::has($setting->getTable(), $badgeColumns)) {
                return;
            }

            $itemModel = static::itemModel();
            $itemTable = (new $itemModel)->getTable();

            if (! TableColumns::has($itemTable, $badgeColumns)) {
                return;
            }

            $profileIds = [];
            $values = TableColumns::only($itemTable, static::settingValues($setting));

            foreach ($itemModel::query()
                ->where('resource_class', $setting->resource_class)
                ->whereHas('profile', fn ($query) => $query
                    ->where('panel_id', $setting->panel_id)
                    ->where('is_default', true))
                ->get() as $profileItem) {
                $profileItem->fill($values);

                if (! $profileItem->isDirty()) {
                    continue;
                }

                $profileItem->save();
                $profileIds[] = $profileItem->profile_id;
            }

            if ($profileIds !== []) {
                static::profileModel()::query()
                    ->whereKey(array_values(array_unique($profileIds)))
                    ->update(['status' => 'draft']);
            }
        } catch (Throwable) {
            // Mirroring must never make saving the resource setting fail.
        }
    }

    /**
     * @deprecated Use syncSetting(), which mirrors every overridable attribute.
     */
    public static function syncBadgeSetting(Model $setting): void
    {
        static::syncSetting($setting);
    }

    /**
     * The legacy table records "no group override" as a null group; profile
     * items carry an explicit flag instead, so the two have to be translated.
     *
     * @return array<string, mixed>
     */
    protected static function settingValues(Model $setting): array
    {
        return [
            'label' => $setting->label,
            'icon' => $setting->icon,
            'active_icon' => $setting->active_icon,
            'navigation_group' => $setting->navigation_group,
            'navigation_group_overridden' => (bool) $setting->navigation_group_overridden
                || filled($setting->navigation_group),
            'parent_resource_class' => $setting->parent_resource_class,
            'sort' => $setting->sort,
            'is_visible' => (bool) $setting->is_visible,
            'badge' => $setting->badge,
            'badge_type' => $setting->badge_type ?: 'static',
            'badge_model' => $setting->badge_model,
            'badge_conditions' => $setting->badge_conditions,
            'badge_color' => $setting->badge_color,
            'badge_tooltip' => $setting->badge_tooltip,
        ];
    }

    public static function publish(Model $profile, mixed $actor = null): Model
    {
        return DB::transaction(function () use ($profile, $actor): Model {
            $versionNumber = ((int) $profile->versions()->lockForUpdate()->max('version')) + 1;
            [$actorType, $actorId] = static::actorIdentity($actor ?? Auth::user());
            $version = $profile->versions()->create([
                'version' => $versionNumber,
                'snapshot' => static::snapshot($profile),
                'published_by_type' => $actorType,
                'published_by_id' => $actorId,
                'published_at' => now(),
            ]);

            $profile->forceFill([
                'status' => 'published',
                'is_default' => true,
                'published_version_id' => $version->getKey(),
                'published_at' => $version->published_at,
            ])->save();

            static::profileModel()::query()
                ->where('panel_id', $profile->panel_id)
                ->whereKeyNot($profile->getKey())
                ->update(['is_default' => false]);

            ProfileResolver::flush();
            OverrideRepository::flush();

            return $version;
        });
    }

    public static function rollback(Model $profile, int $versionId, mixed $actor = null): Model
    {
        $version = $profile->versions()->whereKey($versionId)->firstOrFail();

        DB::transaction(function () use ($profile, $version): void {
            $profile->items()->delete();

            foreach ((array) $version->snapshot as $item) {
                $profile->items()->create(array_intersect_key(
                    (array) $item,
                    array_flip(static::ITEM_ATTRIBUTES),
                ));
            }

            static::mirrorToSettings($profile);
        });

        return static::publish($profile->fresh(), $actor);
    }

    /** @return array<int, array<string, mixed>> */
    public static function snapshot(Model $profile): array
    {
        return $profile->items()
            ->where('is_orphaned', false)
            ->orderBy('sort')
            ->get()
            ->map(fn ($item): array => $item->only(static::ITEM_ATTRIBUTES))
            ->values()
            ->all();
    }

    protected static function seedFromLegacy(Model $profile): void
    {
        $rows = static::resourceSettingModel()::query()
            ->where('panel_id', $profile->panel_id)
            ->where('is_orphaned', false)
            ->orderBy('sort')
            ->get();
        $resourcesByLabel = $rows
            ->filter(fn ($row): bool => filled($row->effective_label))
            ->mapWithKeys(fn ($row): array => [$row->effective_label => $row->resource_class]);

        foreach ($rows as $row) {
            $profile->items()->create([
                'resource_class' => $row->resource_class,
                'label' => $row->label,
                'icon' => $row->icon,
                'active_icon' => $row->active_icon,
                'navigation_group' => $row->navigation_group,
                'navigation_group_overridden' => (bool) $row->navigation_group_overridden
                    || filled($row->navigation_group),
                'parent_resource_class' => $row->parent_resource_class
                    ?: $resourcesByLabel->get($row->navigation_parent_item),
                'sort' => $row->sort,
                'badge' => $row->badge,
                'badge_type' => $row->badge_type ?? 'static',
                'badge_model' => $row->badge_model,
                'badge_conditions' => $row->badge_conditions,
                'badge_color' => $row->badge_color,
                'badge_tooltip' => $row->badge_tooltip,
                'is_visible' => $row->is_visible,
                'default_label' => $row->default_label,
                'default_icon' => $row->default_icon,
                'default_navigation_group' => $row->default_navigation_group,
                'is_orphaned' => false,
            ]);
        }
    }

    /** @return array{0: string|null, 1: string|null} */
    protected static function actorIdentity(mixed $actor): array
    {
        if (! is_object($actor)) {
            return [null, null];
        }

        $id = method_exists($actor, 'getAuthIdentifier')
            ? $actor->getAuthIdentifier()
            : (method_exists($actor, 'getKey') ? $actor->getKey() : ($actor->id ?? null));

        return [$actor::class, $id === null ? null : (string) $id];
    }

    protected static function tablesExist(): bool
    {
        try {
            $tables = [
                'profiles' => 'filament_navigation_profiles',
                'items' => 'filament_navigation_profile_items',
                'versions' => 'filament_navigation_profile_versions',
            ];

            foreach ($tables as $table => $default) {
                if (! Schema::hasTable(config(
                    "filament-resource-manager.profiles.tables.{$table}",
                    $default,
                ))) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return class-string<Model> */
    public static function profileModel(): string
    {
        return config('filament-resource-manager.profiles.models.profile', NavigationProfile::class);
    }

    /** @return class-string<Model> */
    public static function itemModel(): string
    {
        return config('filament-resource-manager.profiles.models.item', NavigationProfileItem::class);
    }

    /** @return class-string<Model> */
    public static function versionModel(): string
    {
        return config('filament-resource-manager.profiles.models.version', NavigationProfileVersion::class);
    }

    /** @return class-string<Model> */
    protected static function resourceSettingModel(): string
    {
        return config('filament-resource-manager.model', ResourceSetting::class);
    }
}
