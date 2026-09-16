<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('filament-resource-manager.profiles.tables.versions', 'filament_navigation_profile_versions');

        if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'roles')) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->json('roles')->nullable());
        }
    }

    public function down(): void
    {
        $table = config('filament-resource-manager.profiles.tables.versions', 'filament_navigation_profile_versions');

        if (Schema::hasTable($table) && Schema::hasColumn($table, 'roles')) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('roles'));
        }
    }
};
