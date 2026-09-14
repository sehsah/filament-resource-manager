<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament\V4\Pages;

use Filament\Resources\Pages\Page;
use MahmoudSehsah\FilamentResourceManager\Filament\Concerns\ManagesNavigationStudio;
use MahmoudSehsah\FilamentResourceManager\Filament\V4\ResourceSettingResource;

/**
 * Access is enforced by Filament itself: Resources\Pages\Page uses
 * CanAuthorizeResourceAccess, whose mount and hydrate hooks abort with 403
 * unless ResourceSettingResource::canAccess() passes - which defers to
 * FilamentResourceManagerPlugin::isAuthorized().
 *
 * This page deliberately declares no mount(). It used to call
 * authorizeAccess(), which Filament\Resources\Pages\Page does not declare on
 * any major, so opening the studio was a fatal error. The studio's own setup
 * lives in the trait's mountManagesNavigationStudio() hook.
 */
class NavigationStudio extends Page
{
    use ManagesNavigationStudio;

    protected static string $resource = ResourceSettingResource::class;

    public function getView(): string
    {
        return 'filament-resource-manager::filament.pages.navigation-studio';
    }
}
