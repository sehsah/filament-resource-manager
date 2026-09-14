<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use Throwable;

/**
 * Models that administrators may use as the source of a dynamic badge count.
 *
 * Resource models registered on the current panel are included automatically.
 * Applications can expose additional models through the package config.
 */
class ModelCatalog
{
    /** @return array<class-string<Model>, string> */
    public static function options(): array
    {
        $models = [];

        foreach ((array) config('filament-resource-manager.dynamic_badges.models', []) as $key => $value) {
            $class = is_int($key) ? $value : $key;
            $label = is_int($key) ? null : $value;

            if (! is_string($class) || ! static::isModel($class)) {
                continue;
            }

            $models[$class] = is_string($label) && filled($label)
                ? $label
                : static::label($class);
        }

        foreach (ResourceDiscovery::resourcesFor(ResourceDiscovery::panel()) as $resource) {
            try {
                $model = $resource::getModel();

                if (is_string($model) && static::isModel($model)) {
                    $models[$model] ??= static::label($model);
                }
            } catch (Throwable) {
                // A resource with an unavailable model should not break the form.
            }
        }

        asort($models, SORT_NATURAL | SORT_FLAG_CASE);

        return $models;
    }

    public static function contains(?string $model): bool
    {
        return is_string($model) && array_key_exists($model, static::options());
    }

    /** @return array<string, string> */
    public static function columns(?string $model): array
    {
        if (! static::contains($model)) {
            return [];
        }

        try {
            /** @var Model $instance */
            $instance = new $model;
            $columns = $instance->getConnection()
                ->getSchemaBuilder()
                ->getColumnListing($instance->getTable());

            return collect($columns)
                ->filter(fn ($column): bool => is_string($column) && $column !== '')
                ->mapWithKeys(fn (string $column): array => [
                    $column => Str::headline($column),
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected static function isModel(string $class): bool
    {
        try {
            return class_exists($class)
                && is_subclass_of($class, Model::class)
                && ! (new ReflectionClass($class))->isAbstract();
        } catch (Throwable) {
            return false;
        }
    }

    protected static function label(string $class): string
    {
        return Str::headline(class_basename($class)).' · '.$class;
    }
}
