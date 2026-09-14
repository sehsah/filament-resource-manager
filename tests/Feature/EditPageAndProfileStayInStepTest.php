<?php

namespace MahmoudSehsah\FilamentResourceManager\Tests\Feature;

use MahmoudSehsah\FilamentResourceManager\Models\ResourceSetting;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileManager;
use MahmoudSehsah\FilamentResourceManager\Tests\TestCase;

/**
 * The resource edit page and the Navigation Studio write to two different
 * tables, and once a profile is published the profile's snapshot is what
 * renders. These cover the mirroring that keeps the two from diverging.
 */
class EditPageAndProfileStayInStepTest extends TestCase
{
    private const USERS = 'App\\Filament\\Resources\\UserResource';

    private const INVOICES = 'App\\Filament\\Resources\\InvoiceResource';

    public function test_editing_a_resource_reaches_the_default_profile_draft(): void
    {
        $this->seedResources();
        $profile = ProfileManager::ensureDefault('admin');
        ProfileManager::publish($profile);

        $setting = ResourceSetting::query()->where('resource_class', self::USERS)->firstOrFail();
        $setting->forceFill([
            'label' => 'Team',
            'icon' => 'heroicon-o-users',
            'navigation_group' => 'Operations',
            'navigation_group_overridden' => true,
            'sort' => 7,
            'is_visible' => false,
        ])->save();

        $item = $profile->items()->where('resource_class', self::USERS)->firstOrFail();

        $this->assertSame('Team', $item->label);
        $this->assertSame('heroicon-o-users', $item->icon);
        $this->assertSame('Operations', $item->navigation_group);
        $this->assertTrue((bool) $item->navigation_group_overridden);
        $this->assertSame(7, $item->sort);
        $this->assertFalse((bool) $item->is_visible);

        // The published snapshot is immutable, so the change waits for a publish.
        $this->assertSame('draft', $profile->fresh()->status);
        $this->assertNull(collect($profile->fresh()->publishedVersion->snapshot)
            ->firstWhere('resource_class', self::USERS)['label']);
    }

    public function test_a_layout_saved_in_the_studio_reaches_the_settings_table(): void
    {
        $this->seedResources();
        $profile = ProfileManager::ensureDefault('admin');

        ProfileManager::saveLayout($profile, [
            [
                'resource_class' => self::INVOICES,
                'navigation_group' => 'Finance',
                'parent_resource_class' => null,
                'is_visible' => false,
            ],
            [
                'resource_class' => self::USERS,
                'navigation_group' => null,
                'parent_resource_class' => self::INVOICES,
                'is_visible' => true,
            ],
        ]);

        $invoices = ResourceSetting::query()->where('resource_class', self::INVOICES)->firstOrFail();
        $users = ResourceSetting::query()->where('resource_class', self::USERS)->firstOrFail();

        $this->assertSame('Finance', $invoices->navigation_group);
        $this->assertSame(1, $invoices->sort);
        $this->assertFalse((bool) $invoices->is_visible);

        $this->assertSame(self::INVOICES, $users->parent_resource_class);
        $this->assertSame(2, $users->sort);

        // Dropped in the studio's Ungrouped bucket: no group at all, which is
        // not the same as "fall back to the resource's own group".
        $this->assertNull($users->navigation_group);
        $this->assertTrue((bool) $users->navigation_group_overridden);
        $this->assertNull($users->effective_navigation_group);
    }

    public function test_a_non_default_profile_keeps_its_own_layout(): void
    {
        $this->seedResources();
        $default = ProfileManager::ensureDefault('admin');
        ProfileManager::publish($default);

        $alternative = ProfileManager::create('admin', 'Support', $default);
        ProfileManager::saveLayout($alternative, [
            [
                'resource_class' => self::USERS,
                'navigation_group' => 'Support desk',
                'parent_resource_class' => null,
                'is_visible' => true,
            ],
        ]);

        ResourceSetting::query()
            ->where('resource_class', self::USERS)
            ->firstOrFail()
            ->forceFill(['navigation_group' => 'Operations', 'navigation_group_overridden' => true])
            ->save();

        $this->assertSame('Support desk', $alternative->items()
            ->where('resource_class', self::USERS)
            ->value('navigation_group'));
        $this->assertSame('Operations', $default->items()
            ->where('resource_class', self::USERS)
            ->value('navigation_group'));
    }

    public function test_the_group_is_only_defaulted_when_it_is_not_overridden(): void
    {
        $setting = new ResourceSetting([
            'navigation_group' => null,
            'default_navigation_group' => 'People',
        ]);

        $this->assertSame('People', $setting->effective_navigation_group);

        $setting->navigation_group_overridden = true;
        $this->assertNull($setting->effective_navigation_group);
        $this->assertFalse($setting->isPassthrough());
    }

    protected function seedResources(): void
    {
        ResourceSetting::query()->create([
            'panel_id' => 'admin',
            'resource_class' => self::USERS,
            'default_label' => 'Users',
            'default_navigation_group' => 'People',
            'sort' => 1,
            'is_visible' => true,
        ]);
        ResourceSetting::query()->create([
            'panel_id' => 'admin',
            'resource_class' => self::INVOICES,
            'default_label' => 'Invoices',
            'default_navigation_group' => 'Finance',
            'sort' => 2,
            'is_visible' => true,
        ]);
    }
}
