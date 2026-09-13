<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

/**
 * Resolves the few classes that moved namespaces in Filament v4.
 *
 * Everything else the package touches is identical across v3, v4 and v5, so this
 * is the single place where a class name depends on the installed major.
 */
class Compat
{
    /**
     * Layout section: Filament\Forms\Components\Section on v3,
     * Filament\Schemas\Components\Section on v4/v5.
     *
     * @return class-string
     */
    public static function sectionClass(): string
    {
        return FilamentVersion::isSchemaBased()
            ? 'Filament\\Schemas\\Components\\Section'
            : 'Filament\\Forms\\Components\\Section';
    }

    /**
     * Table row edit action: Filament\Tables\Actions\EditAction on v3,
     * Filament\Actions\EditAction on v4/v5.
     *
     * @return class-string
     */
    public static function editActionClass(): string
    {
        return FilamentVersion::isSchemaBased()
            ? 'Filament\\Actions\\EditAction'
            : 'Filament\\Tables\\Actions\\EditAction';
    }

    public static function section(?string $heading = null): object
    {
        $class = static::sectionClass();

        return $class::make($heading);
    }

    public static function editAction(): object
    {
        $class = static::editActionClass();

        return $class::make();
    }
}
