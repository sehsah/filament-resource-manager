<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The settings table recorded "no group override" as a null group, which left
 * no way to say "this resource belongs to no group at all" - the state the
 * Navigation Studio's Ungrouped bucket means. Profile items already carried
 * this flag; this brings the settings table in line so the two screens can
 * mirror each other losslessly.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = $this->tableName();

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'navigation_group_overridden')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->boolean('navigation_group_overridden')->default(false);
        });

        // Existing rows with a group set were overriding it by definition.
        DB::table($table)
            ->whereNotNull('navigation_group')
            ->where('navigation_group', '!=', '')
            ->update(['navigation_group_overridden' => true]);
    }

    public function down(): void
    {
        $table = $this->tableName();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'navigation_group_overridden')) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('navigation_group_overridden'));
    }

    protected function tableName(): string
    {
        return config('filament-resource-manager.table_name', 'filament_resource_settings');
    }
};
