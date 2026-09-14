<?php

namespace MahmoudSehsah\FilamentResourceManager\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Schema;
use MahmoudSehsah\FilamentResourceManager\Filament\V4\Pages\NavigationStudio;
use MahmoudSehsah\FilamentResourceManager\Models\ResourceSetting;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileManager;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileResolver;
use MahmoudSehsah\FilamentResourceManager\Tests\TestCase;

class ProfileManagerTest extends TestCase
{
    public function test_profile_schema_is_installed(): void
    {
        $this->assertTrue(Schema::hasColumns('filament_resource_settings', [
            'parent_resource_class',
            'default_navigation_group',
            'badge_type',
            'badge_model',
            'badge_conditions',
        ]));
        $this->assertTrue(Schema::hasTable('filament_navigation_profiles'));
        $this->assertTrue(Schema::hasTable('filament_navigation_profile_items'));
        $this->assertTrue(Schema::hasColumns('filament_navigation_profile_items', [
            'badge_type',
            'badge_model',
            'badge_conditions',
        ]));
        $this->assertFalse(Schema::hasTable('filament_navigation_profile_assignments'));
        $this->assertTrue(Schema::hasTable('filament_navigation_profile_versions'));
    }

    public function test_drafts_publish_immutable_versions_and_rollback_as_a_new_version(): void
    {
        $this->seedResources();
        $profile = ProfileManager::ensureDefault('admin');

        $this->assertNotNull($profile);
        $this->assertCount(2, $profile->items);

        ProfileManager::saveLayout($profile, [
            [
                'resource_class' => 'App\\Filament\\Resources\\UserResource',
                'navigation_group' => 'Operations',
                'parent_resource_class' => null,
                'is_visible' => true,
            ],
            [
                'resource_class' => 'App\\Filament\\Resources\\InvoiceResource',
                'navigation_group' => 'Operations',
                'parent_resource_class' => 'App\\Filament\\Resources\\UserResource',
                'is_visible' => true,
            ],
        ]);

        $versionOne = ProfileManager::publish($profile->fresh());
        $this->assertSame(1, $versionOne->version);
        $this->assertSame(
            'App\\Filament\\Resources\\UserResource',
            collect($versionOne->snapshot)->firstWhere(
                'resource_class',
                'App\\Filament\\Resources\\InvoiceResource',
            )['parent_resource_class'],
        );

        ProfileManager::saveLayout($profile->fresh(), [
            [
                'resource_class' => 'App\\Filament\\Resources\\InvoiceResource',
                'navigation_group' => 'Finance',
                'parent_resource_class' => null,
                'is_visible' => false,
            ],
            [
                'resource_class' => 'App\\Filament\\Resources\\UserResource',
                'navigation_group' => 'People',
                'parent_resource_class' => null,
                'is_visible' => true,
            ],
        ]);

        $this->assertSame('draft', $profile->fresh()->status);
        $this->assertSame(
            'Operations',
            collect($versionOne->fresh()->snapshot)->firstWhere(
                'resource_class',
                'App\\Filament\\Resources\\InvoiceResource',
            )['navigation_group'],
        );

        $rollback = ProfileManager::rollback($profile->fresh(), $versionOne->getKey());
        $this->assertSame(2, $rollback->version);
        $this->assertSame($rollback->getKey(), $profile->fresh()->published_version_id);
        $this->assertSame(
            'Operations',
            $profile->fresh()->items()->where(
                'resource_class',
                'App\\Filament\\Resources\\InvoiceResource',
            )->value('navigation_group'),
        );
    }

    public function test_publishing_a_profile_makes_it_active_for_the_panel(): void
    {
        $this->seedResources();
        $default = ProfileManager::ensureDefault('admin');
        ProfileManager::publish($default);

        $support = ProfileManager::create('admin', 'Support', $default);
        ProfileManager::publish($support);

        $panel = Panel::make()->id('admin');
        Filament::setCurrentPanel($panel);
        ProfileResolver::flush();

        $this->assertSame($support->getKey(), ProfileResolver::resolve($panel)?->getKey());
        $this->assertFalse((bool) $default->fresh()->is_default);
        $this->assertTrue((bool) $support->fresh()->is_default);
    }

    public function test_old_versions_can_be_deleted_but_the_current_version_is_protected(): void
    {
        $this->seedResources();
        $profile = ProfileManager::ensureDefault('admin');
        $versionOne = ProfileManager::publish($profile);
        $versionTwo = ProfileManager::publish($profile->fresh());

        $this->assertFalse(ProfileManager::deleteVersion($profile, $versionTwo->getKey()));
        $this->assertTrue(ProfileManager::deleteVersion($profile, $versionOne->getKey()));
        $this->assertDatabaseMissing('filament_navigation_profile_versions', ['id' => $versionOne->getKey()]);
        $this->assertDatabaseHas('filament_navigation_profile_versions', ['id' => $versionTwo->getKey()]);

        $versionThree = ProfileManager::publish($profile->fresh());

        $this->assertSame(1, ProfileManager::deleteOldVersions($profile));
        $this->assertDatabaseMissing('filament_navigation_profile_versions', ['id' => $versionTwo->getKey()]);
        $this->assertDatabaseHas('filament_navigation_profile_versions', ['id' => $versionThree->getKey()]);
    }

    public function test_editing_a_resource_badge_updates_profile_drafts(): void
    {
        $this->seedResources();
        $profile = ProfileManager::ensureDefault('admin');
        $setting = ResourceSetting::query()
            ->where('resource_class', 'App\\Filament\\Resources\\UserResource')
            ->firstOrFail();

        $setting->forceFill([
            'badge_type' => 'dynamic',
            'badge_model' => 'App\\Models\\User',
            'badge_conditions' => [
                ['column' => 'active', 'operator' => 'is_true', 'value' => null],
            ],
        ])->save();

        $item = $profile->items()
            ->where('resource_class', $setting->resource_class)
            ->firstOrFail();

        $this->assertSame('dynamic', $item->badge_type);
        $this->assertSame('App\\Models\\User', $item->badge_model);
        $this->assertSame($setting->badge_conditions, $item->badge_conditions);
        $this->assertSame('draft', $profile->fresh()->status);
    }

    public function test_navigation_studio_view_is_registered_against_real_filament(): void
    {
        $page = (new \ReflectionClass(NavigationStudio::class))->newInstanceWithoutConstructor();

        $this->assertSame(
            'filament-resource-manager::filament.pages.navigation-studio',
            $page->getView(),
        );
        $this->assertTrue(view()->exists($page->getView()));
    }

    protected function seedResources(): void
    {
        ResourceSetting::query()->create([
            'panel_id' => 'admin',
            'resource_class' => 'App\\Filament\\Resources\\UserResource',
            'default_label' => 'Users',
            'default_navigation_group' => 'People',
            'sort' => 1,
            'is_visible' => true,
        ]);
        ResourceSetting::query()->create([
            'panel_id' => 'admin',
            'resource_class' => 'App\\Filament\\Resources\\InvoiceResource',
            'default_label' => 'Invoices',
            'default_navigation_group' => 'Finance',
            'sort' => 2,
            'is_visible' => true,
        ]);
    }
}
