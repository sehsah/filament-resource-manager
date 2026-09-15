<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament\V4;

use BackedEnum;
use Filament\Panel;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use MahmoudSehsah\FilamentResourceManager\Filament\BaseResourceSettingResource;
use UnitEnum;

/**
 * Filament v4 and v5 variant.
 *
 * Both majors replaced the Form passed to form() with a Schema, widened the
 * navigation accessors to accept enums, and gave getSlug() an optional $panel
 * argument. v4 and v5 are identical to each other, so one class serves both.
 */
class ResourceSettingResource extends BaseResourceSettingResource
{
    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formComponents());
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return static::configuredIcon();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return static::configuredGroup();
    }

    public static function getSlug(?Panel $panel = null): string
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
