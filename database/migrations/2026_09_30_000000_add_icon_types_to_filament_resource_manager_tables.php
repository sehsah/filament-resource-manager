<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a navigation icon be a picked icon, a typed icon name, raw SVG markup or
 * an uploaded image. The original `icon` and `active_icon` columns keep holding
 * icon names; SVG markup is too long for them, so it gets its own text column.
 */
return new class extends Migration
{
    /** @var array<string, string> column => type */
    protected array $columns = [
        'icon_type' => 'string',
        'icon_svg' => 'text',
        'icon_image' => 'string',
        'active_icon_type' => 'string',
        'active_icon_svg' => 'text',
        'active_icon_image' => 'string',
    ];

    public function up(): void
    {
        $this->addColumns(config(
            'filament-resource-manager.table_name',
            'filament_resource_settings',
        ));
        $this->addColumns(config(
            'filament-resource-manager.profiles.tables.items',
            'filament_navigation_profile_items',
        ));
    }

    public function down(): void
    {
        $this->dropColumns(config(
            'filament-resource-manager.profiles.tables.items',
            'filament_navigation_profile_items',
        ));
        $this->dropColumns(config(
            'filament-resource-manager.table_name',
            'filament_resource_settings',
        ));
    }

    protected function addColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $missing = collect($this->columns)
            ->reject(fn (string $type, string $column): bool => Schema::hasColumn($table, $column))
            ->all();

        if ($missing === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($missing): void {
            foreach ($missing as $column => $type) {
                $blueprint->{$type}($column)->nullable();
            }
        });
    }

    protected function dropColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $present = collect(array_keys($this->columns))
            ->filter(fn (string $column): bool => Schema::hasColumn($table, $column))
            ->values()
            ->all();

        if ($present !== []) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($present));
        }
    }
};
