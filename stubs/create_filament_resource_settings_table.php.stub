<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Publishing a migration re-stamps its filename with the current time,
        // so an install that re-publishes to pick up a later migration ends up
        // with a second, differently-named copy of this one that Laravel has no
        // record of running. Checking for the table keeps that copy harmless
        // instead of failing the whole migrate run.
        if (Schema::hasTable($this->tableName())) {
            return;
        }

        Schema::create($this->tableName(), function (Blueprint $table): void {
            $table->id();

            $table->string('panel_id')->nullable()->index();
            $table->string('resource_class');

            $table->string('label')->nullable();
            $table->string('icon')->nullable();
            $table->string('active_icon')->nullable();
            $table->string('navigation_group')->nullable();
            $table->string('navigation_parent_item')->nullable();
            $table->integer('sort')->nullable();
            $table->boolean('is_visible')->default(true);

            $table->string('badge')->nullable();
            $table->string('badge_color')->nullable();
            $table->string('badge_tooltip')->nullable();

            $table->string('default_label')->nullable();
            $table->string('default_icon')->nullable();
            $table->string('default_navigation_group')->nullable();
            $table->boolean('is_orphaned')->default(false);

            $table->timestamps();

            $table->unique(['panel_id', 'resource_class'], 'frs_panel_resource_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName());
    }

    protected function tableName(): string
    {
        return config('filament-resource-manager.table_name', 'filament_resource_settings');
    }
};
