<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Builds a safe Eloquent count for a navigation badge.
 *
 * Model and column names are selected from ModelCatalog, and operators are
 * mapped explicitly. No stored value is ever treated as raw SQL.
 */
class DynamicBadgeResolver
{
    /** @param array<string, mixed> $override */
    public static function resolve(array $override): string|int|null
    {
        if (($override['badge_type'] ?? 'static') !== 'dynamic') {
            return filled($override['badge'] ?? null) ? (string) $override['badge'] : null;
        }

        $model = $override['badge_model'] ?? null;
        $conditions = static::conditions($override['badge_conditions'] ?? []);

        if (! is_string($model) || ! ModelCatalog::contains($model)) {
            return null;
        }

        try {
            /** @var Builder $query */
            $query = $model::query();
            $allowedColumns = array_keys(ModelCatalog::columns($model));

            foreach (array_slice($conditions, 0, 20) as $condition) {
                if (! static::applyCondition($query, $condition, $allowedColumns)) {
                    return null;
                }
            }

            return $query->count();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<int, string>  $allowedColumns
     */
    protected static function applyCondition(Builder $query, array $condition, array $allowedColumns): bool
    {
        $column = $condition['column'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        if (! is_string($column) || ! in_array($column, $allowedColumns, true)) {
            return false;
        }

        return match ($operator) {
            'equals' => (bool) $query->where($column, '=', $value),
            'not_equals' => (bool) $query->where($column, '!=', $value),
            'greater_than' => (bool) $query->where($column, '>', $value),
            'greater_than_or_equal' => (bool) $query->where($column, '>=', $value),
            'less_than' => (bool) $query->where($column, '<', $value),
            'less_than_or_equal' => (bool) $query->where($column, '<=', $value),
            'contains' => (bool) $query->where($column, 'like', '%'.$value.'%'),
            'starts_with' => (bool) $query->where($column, 'like', $value.'%'),
            'ends_with' => (bool) $query->where($column, 'like', '%'.$value),
            'is_null' => (bool) $query->whereNull($column),
            'is_not_null' => (bool) $query->whereNotNull($column),
            'is_true' => (bool) $query->where($column, true),
            'is_false' => (bool) $query->where($column, false),
            default => false,
        };
    }

    /** @return array<int, array<string, mixed>> */
    protected static function conditions(mixed $conditions): array
    {
        if (is_string($conditions)) {
            $conditions = json_decode($conditions, true);
        }

        if (! is_array($conditions)) {
            return [];
        }

        return array_values(array_filter($conditions, 'is_array'));
    }
}
