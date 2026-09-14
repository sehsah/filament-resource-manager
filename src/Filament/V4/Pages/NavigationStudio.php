<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament\V4\Pages;

use Filament\Resources\Pages\Page;
use MahmoudSehsah\FilamentResourceManager\Filament\Concerns\ManagesNavigationStudio;
use MahmoudSehsah\FilamentResourceManager\Filament\V4\ResourceSettingResource;

class NavigationStudio extends Page
{
    use ManagesNavigationStudio;

    protected static string $resource = ResourceSettingResource::class;

    public function mount(): void
    {
    }

    public function getView(): string
    {
        return 'filament-resource-manager::filament.pages.navigation-studio';
    }
}
