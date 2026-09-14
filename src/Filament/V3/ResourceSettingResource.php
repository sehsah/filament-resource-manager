<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament\V3;

use Filament\Forms\Form;
use Illuminate\Contracts\Support\Htmlable;
use MahmoudSehsah\FilamentResourceManager\Filament\BaseResourceSettingResource;

/**
 * Filament v3 variant.
 *
 * On v3, form() receives a Form, getNavigationIcon() returns
 * string|Htmlable|null, getNavigationGroup() returns ?string, and getSlug()
 * takes no arguments. All four changed in v4, so each major gets its own
 * subclass; the field definitions themselves live in the shared base.
 */
class ResourceSettingResource extends BaseResourceSettingResource
{
    public static function form(Form $form): Form
    {
        return $form->schema(static::formComponents());
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return static::configuredIcon();
    }

    public static function getNavigationGroup(): ?string
    {
        return static::configuredGroup();
    }

    public static function getSlug(): string
    {
        return static::configuredSlug();
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResourceSettings::route('/'),
            'edit' => Pages\EditResourceSetting::route('/{record}/edit'),
            'studio' => Pages\NavigationStudio::route('/studio'),
        ];
    }
}
