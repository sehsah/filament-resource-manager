<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Clears the navigation overrides of resource settings rows.
 *
 * Access control (roles, permissions and their conditions) is deliberately left
 * alone: resetting how an item looks must not quietly widen who can see it.
 */
class SettingsReset
{
    /**
     * The reset values, minus any column the table does not have yet.
     *
     * @return array<string, mixed>
     */
    public static function values(string $table): array
    {
        return TableColumns::only($table, [
            'label' => null,
            'icon' => null,
            'active_icon' => null,
            'icon_type' => null,
            'icon_svg' => null,
            'icon_image' => null,
            'active_icon_type' => null,
            'active_icon_svg' => null,
            'active_icon_image' => null,
            'navigation_group' => null,
            'navigation_group_overridden' => false,
            'navigation_parent_item' => null,
            'parent_resource_class' => null,
            'badge' => null,
            'badge_type' => 'static',
            'badge_model' => null,
            'badge_conditions' => null,
            'badge_color' => null,
            'badge_tooltip' => null,
            'is_visible' => true,
        ]);
    }

    public static function one(Model $record): void
    {
        $record->forceFill(static::values($record->getTable()))->save();

        OverrideRepository::flush();
    }

    /**
     * Rows are saved one by one rather than with a single bulk update: the
     * model's saved event is what mirrors each change into the default profile
     * draft, and a bulk update skips it. Cache flushing is suspended so the
     * whole reset flushes once.
     *
     * @return int the number of rows reset
     */
    public static function all(Builder $query): int
    {
        $count = OverrideRepository::withoutFlushing(fn (): int => DB::transaction(function () use ($query): int {
            $records = $query->get();

            foreach ($records as $record) {
                $record->forceFill(static::values($record->getTable()))->save();
            }

            return $records->count();
        }));

        OverrideRepository::flush();

        return $count;
    }
}
