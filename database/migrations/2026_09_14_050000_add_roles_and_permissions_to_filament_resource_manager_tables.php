<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addAccessColumns(config(
            'filament-resource-manager.table_name',
            'filament_resource_settings',
        ));

        $this->addAccessColumns(config(
            'filament-resource-manager.profiles.tables.items',
            'filament_navigation_profile_items',
        ));

        $this->addProfileRoleColumns(config(
            'filament-resource-manager.profiles.tables.profiles',
            'filament_navigation_profiles',
        ));
    }

    public function down(): void
    {
        $this->dropProfileRoleColumns(config(
            'filament-resource-manager.profiles.tables.profiles',
            'filament_navigation_profiles',
        ));

        $this->dropAccessColumns(config(
            'filament-resource-manager.profiles.tables.items',
            'filament_navigation_profile_items',
        ));

        $this->dropAccessColumns(config(
            'filament-resource-manager.table_name',
            'filament_resource_settings',
        ));
    }

    protected function addAccessColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = ['roles', 'permissions', 'roles_condition', 'permissions_condition'];
        $missing = collect($columns)
            ->reject(fn (string $column): bool => Schema::hasColumn($table, $column))
            ->all();

        if ($missing === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($missing): void {
            if (in_array('roles', $missing, true)) {
                $blueprint->json('roles')->nullable();
            }

            if (in_array('permissions', $missing, true)) {
                $blueprint->json('permissions')->nullable();
            }

            if (in_array('roles_condition', $missing, true)) {
                $blueprint->string('roles_condition')->default('any');
            }

            if (in_array('permissions_condition', $missing, true)) {
                $blueprint->string('permissions_condition')->default('any');
            }
        });
    }

    protected function dropAccessColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = ['roles', 'permissions', 'roles_condition', 'permissions_condition'];
        $present = collect($columns)
            ->filter(fn (string $column): bool => Schema::hasColumn($table, $column))
            ->all();

        if ($present !== []) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($present));
        }
    }

    protected function addProfileRoleColumns(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'roles')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->json('roles')->nullable();
        });
    }

    protected function dropProfileRoleColumns(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'roles')) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('roles'));
    }
};
