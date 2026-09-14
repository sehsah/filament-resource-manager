<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Which columns a table actually has.
 *
 * The package adds columns over time, and an application can update the
 * package before it runs the new migrations. Reading or writing a column that
 * is not there yet is an SQL error, so every such column is checked first.
 * Schema introspection costs a query, so answers are remembered per request.
 */
class TableColumns
{
    /** @var array<string, array<int, string>|null> */
    protected static array $columns = [];

    /**
     * @return array<int, string>|null null when the table could not be read
     */
    public static function of(string $table): ?array
    {
        if (array_key_exists($table, static::$columns)) {
            return static::$columns[$table];
        }

        try {
            $columns = Schema::getColumnListing($table);

            return static::$columns[$table] = $columns === [] ? null : $columns;
        } catch (Throwable) {
            return static::$columns[$table] = null;
        }
    }

    /**
     * True when every named column is present. An unreadable table answers
     * true, so a missing column is never mistaken for a broken connection.
     *
     * @param  array<int, string>  $columns
     */
    public static function has(string $table, array $columns): bool
    {
        $present = static::of($table);

        if ($present === null) {
            return true;
        }

        return array_diff($columns, $present) === [];
    }

    /**
     * The given attributes, minus any the table does not have.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function only(string $table, array $values): array
    {
        $present = static::of($table);

        if ($present === null) {
            return $values;
        }

        return array_intersect_key($values, array_flip($present));
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    public static function intersect(string $table, array $names): array
    {
        $present = static::of($table);

        return $present === null
            ? $names
            : array_values(array_intersect($names, $present));
    }

    public static function flush(): void
    {
        static::$columns = [];
    }
}
