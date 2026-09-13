<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use Filament\Facades\Filament;
use Filament\Panel;
use Throwable;

/**
 * Reads the resources registered on a panel and describes how each one currently
 * presents itself in navigation.
 */
class ResourceDiscovery
{
    /**
     * @return array<int, class-string>
     */
    public static function resourcesFor(?Panel $panel = null): array
    {
        $panel = $panel ?? static::panel();

        if (! $panel instanceof Panel) {
            return [];
        }

        $excluded = (array) config('filament-resource-manager.excluded_resources', []);

        $resources = array_values(array_filter(
            $panel->getResources(),
            fn (string $resource): bool => class_exists($resource) && ! in_array($resource, $excluded, true),
        ));

        sort($resources);

        return $resources;
    }

    /**
     * The label a resource declares for itself, ignoring any override.
     */
    public static function defaultLabel(string $resource): ?string
    {
        return static::rescue(function () use ($resource): ?string {
            if (method_exists($resource, 'getTitleCasePluralModelLabel')) {
                return $resource::getTitleCasePluralModelLabel();
            }

            if (method_exists($resource, 'getPluralModelLabel')) {
                return $resource::getPluralModelLabel();
            }

            return null;
        }) ?? class_basename($resource);
    }

    public static function defaultIcon(string $resource): ?string
    {
        $icon = static::rescue(fn () => $resource::getNavigationIcon());

        return static::stringifyIcon($icon);
    }

    public static function defaultGroup(string $resource): ?string
    {
        $group = static::rescue(fn () => $resource::getNavigationGroup());

        if ($group instanceof \UnitEnum) {
            return $group instanceof \BackedEnum ? (string) $group->value : $group->name;
        }

        return is_string($group) ? $group : null;
    }

    public static function defaultSort(string $resource): ?int
    {
        $sort = static::rescue(fn () => $resource::getNavigationSort());

        return is_int($sort) ? $sort : null;
    }

    /**
     * The URL of a resource's navigation entry, used to match navigation items
     * back to their resource on Filament v3 (where items carry no key).
     */
    public static function navigationUrl(string $resource): ?string
    {
        return static::rescue(fn () => $resource::getNavigationUrl());
    }

    /**
     * Icons may be plain strings, backed enums (v4/v5 Heroicon cases) or
     * Htmlable. Only strings and backed enums can round-trip through the
     * database, which covers every icon set Filament ships with.
     */
    public static function stringifyIcon(mixed $icon): ?string
    {
        if (is_string($icon)) {
            return $icon;
        }

        if ($icon instanceof \BackedEnum) {
            $value = (string) $icon->value;

            if ($icon::class === 'Filament\\Support\\Icons\\Heroicon') {
                return str_starts_with($value, 'heroicon-') ? $value : "heroicon-{$value}";
            }

            return $value;
        }

        return null;
    }

    public static function panel(): ?Panel
    {
        return static::rescue(function (): ?Panel {
            if (method_exists(Filament::getFacadeRoot(), 'getCurrentOrDefaultPanel')) {
                return Filament::getCurrentOrDefaultPanel();
            }

            return Filament::getCurrentPanel();
        });
    }

    public static function panelId(?Panel $panel = null): ?string
    {
        $panel = $panel ?? static::panel();

        return $panel instanceof Panel ? $panel->getId() : null;
    }

    /**
     * Resource accessors can throw when a panel has tenancy configured or a
     * resource has no index page. A failing resource must never break
     * navigation, so failures degrade to null.
     */
    protected static function rescue(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return null;
        }
    }
}
