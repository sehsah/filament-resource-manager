<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament\V4\Pages;

use Filament\Resources\Pages\EditRecord;
use MahmoudSehsah\FilamentResourceManager\Filament\Concerns\EditsResourceSetting;
use MahmoudSehsah\FilamentResourceManager\Filament\V4\ResourceSettingResource;

class EditResourceSetting extends EditRecord
{
    use EditsResourceSetting;

    protected static string $resource = ResourceSettingResource::class;
}
