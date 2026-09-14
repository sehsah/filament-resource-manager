<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $profiles = $this->table('profiles', 'filament_navigation_profiles');
        $items = $this->table('items', 'filament_navigation_profile_items');
        $versions = $this->table('versions', 'filament_navigation_profile_versions');

        if (! Schema::hasTable($profiles)) {
            Schema::create($profiles, function (Blueprint $table): void {
                $table->id();
                $table->string('panel_id')->index();
                $table->string('name');
                $table->string('slug');
                $table->string('status')->default('draft');
                $table->boolean('is_default')->default(false);
                $table->unsignedBigInteger('published_version_id')->nullable()->index();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->unique(['panel_id', 'slug'], 'frm_profiles_panel_slug_unique');
            });
        }

        if (! Schema::hasTable($items)) {
            Schema::create($items, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('profile_id')->index();
                $table->string('resource_class');
                $table->string('label')->nullable();
                $table->string('icon')->nullable();
                $table->string('active_icon')->nullable();
                $table->string('navigation_group')->nullable();
                $table->boolean('navigation_group_overridden')->default(false);
                $table->string('parent_resource_class')->nullable();
                $table->integer('sort')->nullable();
                $table->boolean('is_visible')->default(true);
                $table->string('badge')->nullable();
                $table->string('badge_type')->default('static');
                $table->string('badge_model')->nullable();
                $table->json('badge_conditions')->nullable();
                $table->string('badge_color')->nullable();
                $table->string('badge_tooltip')->nullable();
                $table->string('default_label')->nullable();
                $table->string('default_icon')->nullable();
                $table->string('default_navigation_group')->nullable();
                $table->boolean('is_orphaned')->default(false);
                $table->timestamps();

                $table->unique(['profile_id', 'resource_class'], 'frm_items_profile_resource_unique');
            });
        }

        if (! Schema::hasTable($versions)) {
            Schema::create($versions, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('profile_id')->index();
                $table->unsignedInteger('version');
                $table->json('snapshot');
                $table->string('published_by_type')->nullable();
                $table->string('published_by_id')->nullable();
                $table->timestamp('published_at');
                $table->timestamps();

                $table->unique(['profile_id', 'version'], 'frm_versions_profile_version_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('versions', 'filament_navigation_profile_versions'));
        Schema::dropIfExists($this->table('items', 'filament_navigation_profile_items'));
        Schema::dropIfExists($this->table('profiles', 'filament_navigation_profiles'));
    }

    protected function table(string $key, string $default): string
    {
        return config("filament-resource-manager.profiles.tables.{$key}", $default);
    }
};
