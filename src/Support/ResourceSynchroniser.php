<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use MahmoudSehsah\FilamentResourceManager\Models\ResourceSetting;

/**
 * Keeps the settings table in step with the resources actually registered on a
 * panel: adds rows for new resources, refreshes the recorded defaults, and flags
 * rows whose resource class has gone away.
 */
class ResourceSynchroniser
{
    /**
     * @return array{created: int, updated: int, orphaned: int}
     */
    public static function sync(?Panel $panel = null): array
    {
        // Each row saved below clears the override cache through the model's
        // save event. Suspending that collapses a whole panel's worth of writes
        // into the single flush at the end.
        $result = OverrideRepository::withoutFlushing(
            static fn (): array => static::run($panel),
        );

        OverrideRepository::flush();

        return $result;
    }

    /**
     * @return array{created: int, updated: int, orphaned: int}
     */
    protected static function run(?Panel $panel): array
    {
        $panel = $panel ?? ResourceDiscovery::panel();
        $panelId = ResourceDiscovery::panelId($panel);

        $resources = ResourceDiscovery::resourcesFor($panel);

        $created = 0;
        $updated = 0;

        $existing = static::query()
            ->where('panel_id', $panelId)
            ->get()
            ->keyBy('resource_class');

        $highestSort = (int) static::query()->where('panel_id', $panelId)->max('sort');

        foreach ($resources as $resource) {
            $defaults = [
                'default_label' => ResourceDiscovery::defaultLabel($resource),
                'default_icon' => ResourceDiscovery::defaultIcon($resource),
                'is_orphaned' => false,
            ];

            /** @var ResourceSetting|null $row */
            $row = $existing->get($resource);

            if ($row === null) {
                static::query()->create([
                    'panel_id' => $panelId,
                    'resource_class' => $resource,
                    'navigation_group' => ResourceDiscovery::defaultGroup($resource),
                    'sort' => ResourceDiscovery::defaultSort($resource) ?? ++$highestSort,
                    'is_visible' => true,
                    ...$defaults,
                ]);

                $created++;

                continue;
            }

            $row->fill($defaults);

            if ($row->isDirty()) {
                $row->save();
                $updated++;
            }
        }

        $orphaned = static::query()
            ->where('panel_id', $panelId)
            ->when($resources !== [], fn ($query) => $query->whereNotIn('resource_class', $resources))
            ->where('is_orphaned', false)
            ->update(['is_orphaned' => true]);

        return [
            'created' => $created,
            'updated' => $updated,
            'orphaned' => (int) $orphaned,
        ];
    }

    protected static function query()
    {
        /** @var class-string<Model> $model */
        $model = config('filament-resource-manager.model', ResourceSetting::class);

        return $model::query();
    }
}
