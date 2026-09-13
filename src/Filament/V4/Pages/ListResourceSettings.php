<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament\V4\Pages;

use Filament\Resources\Pages\ListRecords;
use MahmoudSehsah\FilamentResourceManager\Filament\Concerns\ListsResourceSettings;
use MahmoudSehsah\FilamentResourceManager\Filament\V4\ResourceSettingResource;

class ListResourceSettings extends ListRecords
{
    use ListsResourceSettings;

    protected static string $resource = ResourceSettingResource::class;
}
