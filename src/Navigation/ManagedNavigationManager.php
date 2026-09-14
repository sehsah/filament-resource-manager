<?php

namespace MahmoudSehsah\FilamentResourceManager\Navigation;

use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationManager;
use MahmoudSehsah\FilamentResourceManager\Support\DynamicBadgeResolver;
use MahmoudSehsah\FilamentResourceManager\Support\OverrideRepository;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceDiscovery;
use Throwable;

/**
 * Applies the stored overrides to the panel's navigation.
 *
 * Filament resolves Filament\Navigation\NavigationManager from the container as
 * a scoped binding, in Panel::getNavigation(). This subclass replaces that
 * binding, mounts the navigation as usual so every resource registers its item,
 * then rewrites those items before the parent groups and sorts them.
 *
 * Rewriting the NavigationItem objects is deliberate. The obvious alternative -
 * assigning Resource::$navigationLabel and friends - is unsafe: those are
 * protected static properties on the base Filament Resource class, and a
 * resource that does not redeclare one shares the base class's storage, so
 * writing it would leak the value into every other resource. Each navigation
 * item, by contrast, is its own object.
 *
 * The class layout of NavigationManager is identical in Filament v3, v4 and v5,
 * so one implementation serves all three.
 */
class ManagedNavigationManager extends NavigationManager
{
    /**
     * @return array<mixed>
     */
    public function get(): array
    {
        try {
            if (! $this->isNavigationMounted) {
                $this->mountNavigation();
            }

            $this->applyResourceOverrides();
        } catch (Throwable) {
            // Never let this package break a panel's navigation.
        }

        return parent::get();
    }

    protected function applyResourceOverrides(): void
    {
        $panel = $this->panel ?? ResourceDiscovery::panel();

        if ($panel === null) {
            return;
        }

        $overrides = OverrideRepository::forPanel($panel->getId());

        if ($overrides === []) {
            return;
        }

        $byKey = [];
        $byUrl = [];

        foreach (ResourceDiscovery::resourcesFor($panel) as $resource) {
            if (! array_key_exists($resource, $overrides)) {
                continue;
            }

            $byKey[$resource] = $resource;

            $url = ResourceDiscovery::navigationUrl($resource);

            if (filled($url)) {
                $byUrl[$url] = $resource;
            }
        }

        $labelsByResource = [];

        foreach ($overrides as $resource => $override) {
            $labelsByResource[$resource] = filled($override['label'] ?? null)
                ? (string) $override['label']
                : ResourceDiscovery::defaultLabel($resource);
        }

        foreach ($this->navigationItems as $item) {
            $resource = $this->resolveResource($item, $byKey, $byUrl);

            if ($resource === null) {
                continue;
            }

            $this->applyOverride($item, $overrides[$resource], $labelsByResource);
        }
    }

    /**
     * Newer Filament releases tag each resource's navigation item with the
     * resource class name as its key; v3 and the 4.x line up to at least 4.11
     * have no key at all. The URL the item points at is set from
     * Resource::getNavigationUrl() in every version, so it is the reliable
     * fallback and the path actually taken on most installs.
     *
     * @param  array<string, string>  $byKey
     * @param  array<string, string>  $byUrl
     */
    protected function resolveResource(NavigationItem $item, array $byKey, array $byUrl): ?string
    {
        if (method_exists($item, 'getKey')) {
            $key = $item->getKey();

            if (is_string($key) && isset($byKey[$key])) {
                return $byKey[$key];
            }
        }

        $url = $item->getUrl();

        if (is_string($url) && isset($byUrl[$url])) {
            return $byUrl[$url];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $override
     */
    protected function applyOverride(
        NavigationItem $item,
        array $override,
        array $labelsByResource = [],
    ): void {
        if (($override['is_visible'] ?? true) === false) {
            $item->hidden();

            return;
        }

        if (filled($override['label'] ?? null)) {
            $item->label($override['label']);
        }

        if (filled($override['icon'] ?? null)) {
            $item->icon($override['icon']);
        }

        if (filled($override['active_icon'] ?? null)) {
            $item->activeIcon($override['active_icon']);
        }

        if (($override['navigation_group_overridden'] ?? false) === true) {
            $item->group($override['navigation_group'] ?? null);
        } elseif (filled($override['navigation_group'] ?? null)) {
            $item->group($override['navigation_group']);
        }

        $parentResource = $override['parent_resource_class'] ?? null;

        if (is_string($parentResource) && filled($labelsByResource[$parentResource] ?? null)) {
            $item->parentItem($labelsByResource[$parentResource]);
        } elseif (filled($override['navigation_parent_item'] ?? null)) {
            $item->parentItem($override['navigation_parent_item']);
        }

        if (($override['sort'] ?? null) !== null) {
            $item->sort((int) $override['sort']);
        }

        $badge = DynamicBadgeResolver::resolve($override);

        if ($badge !== null) {
            $item->badge((string) $badge, color: $override['badge_color'] ?? null);
        }

        if (filled($override['badge_tooltip'] ?? null)) {
            $item->badgeTooltip($override['badge_tooltip']);
        }
    }
}
