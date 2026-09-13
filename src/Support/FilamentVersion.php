<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use Composer\InstalledVersions;

/**
 * Detects which major version of Filament is installed.
 *
 * The package supports v3, v4 and v5. Their APIs are close enough that a single
 * implementation covers almost everything; the one exception is the signature of
 * Resource::form(), which takes a Form in v3 and a Schema in v4/v5.
 */
class FilamentVersion
{
    protected static ?int $major = null;

    public static function major(): int
    {
        if (static::$major !== null) {
            return static::$major;
        }

        return static::$major = static::detect();
    }

    public static function isV3(): bool
    {
        return static::major() === 3;
    }

    /**
     * v4 and v5 share the schema-based API, so they are handled together.
     */
    public static function isSchemaBased(): bool
    {
        return static::major() >= 4;
    }

    /**
     * Only intended for tests.
     */
    public static function fake(?int $major): void
    {
        static::$major = $major;
    }

    protected static function detect(): int
    {
        if (class_exists(InstalledVersions::class)) {
            try {
                $version = InstalledVersions::getPrettyVersion('filament/filament');

                if (is_string($version) && preg_match('/(\d+)/', ltrim($version, 'v'), $matches) === 1) {
                    $major = (int) $matches[1];

                    if ($major >= 3) {
                        return $major;
                    }
                }
            } catch (\Throwable) {
                // Fall through to class-based detection below.
            }
        }

        // Filament v4 introduced Filament\Schemas\Schema; v3 has no such class.
        return class_exists(\Filament\Schemas\Schema::class) ? 4 : 3;
    }
}
