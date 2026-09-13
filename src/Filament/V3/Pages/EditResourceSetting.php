<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament\V3\Pages;

use Filament\Resources\Pages\EditRecord;
use MahmoudSehsah\FilamentResourceManager\Filament\Concerns\EditsResourceSetting;
use MahmoudSehsah\FilamentResourceManager\Filament\V3\ResourceSettingResource;

class EditResourceSetting extends EditRecord
{
    use EditsResourceSetting;

    protected static string $resource = ResourceSettingResource::class;
}
