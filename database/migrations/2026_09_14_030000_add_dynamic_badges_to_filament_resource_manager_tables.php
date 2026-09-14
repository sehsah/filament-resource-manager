<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

        $missing = collect(['badge_type', 'badge_model', 'badge_conditions'])
            ->reject(fn (string $column): bool => Schema::hasColumn($table, $column))
            ->all();

        if ($missing === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($missing): void {
            if (in_array('badge_type', $missing, true)) {
                $blueprint->string('badge_type')->default('static');
            }

            if (in_array('badge_model', $missing, true)) {
                $blueprint->string('badge_model')->nullable();
            }

            if (in_array('badge_conditions', $missing, true)) {
                $blueprint->json('badge_conditions')->nullable();
            }
        });
    }

    protected function dropColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $present = collect(['badge_type', 'badge_model', 'badge_conditions'])
            ->filter(fn (string $column): bool => Schema::hasColumn($table, $column))
            ->all();

        if ($present !== []) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($present));
        }
    }
};
