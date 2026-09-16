<?php

namespace MahmoudSehsah\FilamentResourceManager\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use MahmoudSehsah\FilamentResourceManager\FilamentResourceManagerPlugin;
use MahmoudSehsah\FilamentResourceManager\Models\NavigationProfile;
use MahmoudSehsah\FilamentResourceManager\Models\ResourceSetting;
use MahmoudSehsah\FilamentResourceManager\Navigation\ManagedNavigationManager;
use MahmoudSehsah\FilamentResourceManager\Support\AccessResolver;
use MahmoudSehsah\FilamentResourceManager\Support\OverrideRepository;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileManager;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileResolver;
use MahmoudSehsah\FilamentResourceManager\Tests\Fixtures\TestUser;
use MahmoudSehsah\FilamentResourceManager\Tests\TestCase;

class RoleAndPermissionTest extends TestCase
{
    private const USERS = 'App\\Filament\\Resources\\UserResource';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Panel::make()->id('admin'));
        ProfileResolver::flush();
        OverrideRepository::flush();
    }

    public function test_access_resolver_allows_unrestricted_item_to_everyone(): void
    {
        $override = [
            'is_visible' => true,
            'roles' => [],
            'permissions' => [],
        ];

        $this->assertTrue(AccessResolver::canAccessItem($override, null));

        $user = new TestUser;
        $this->assertTrue(AccessResolver::canAccessItem($override, $user));
    }

    public function test_access_resolver_blocks_hidden_item_regardless_of_roles(): void
    {
        $override = [
            'is_visible' => false,
            'roles' => ['admin'],
        ];

        $user = new TestUser;
        $user->assignedRoles = ['admin'];

        $this->assertFalse(AccessResolver::canAccessItem($override, $user));
    }

    public function test_access_resolver_requires_authenticated_user_when_roles_configured(): void
    {
        $override = [
            'is_visible' => true,
            'roles' => ['admin'],
        ];

        $this->assertFalse(AccessResolver::canAccessItem($override, null));
    }

    public function test_access_resolver_evaluates_roles_with_any_condition(): void
    {
        $override = [
            'is_visible' => true,
            'roles' => ['admin', 'manager'],
            'roles_condition' => 'any',
        ];

        $admin = new TestUser;
        $admin->assignedRoles = ['admin'];
        $this->assertTrue(AccessResolver::canAccessItem($override, $admin));

        $manager = new TestUser;
        $manager->assignedRoles = ['manager'];
        $this->assertTrue(AccessResolver::canAccessItem($override, $manager));

        $guest = new TestUser;
        $guest->assignedRoles = ['editor'];
        $this->assertFalse(AccessResolver::canAccessItem($override, $guest));
    }

    public function test_access_resolver_evaluates_roles_with_all_condition(): void
    {
        $override = [
            'is_visible' => true,
            'roles' => ['admin', 'finance'],
            'roles_condition' => 'all',
        ];

        $adminOnly = new TestUser;
        $adminOnly->assignedRoles = ['admin'];
        $this->assertFalse(AccessResolver::canAccessItem($override, $adminOnly));

        $both = new TestUser;
        $both->assignedRoles = ['admin', 'finance'];
        $this->assertTrue(AccessResolver::canAccessItem($override, $both));
    }

    public function test_access_resolver_evaluates_permissions_with_any_and_all_conditions(): void
    {
        $overrideAny = [
            'is_visible' => true,
            'permissions' => ['view_users', 'edit_users'],
            'permissions_condition' => 'any',
        ];

        $viewer = new TestUser;
        $viewer->assignedPermissions = ['view_users'];
        $this->assertTrue(AccessResolver::canAccessItem($overrideAny, $viewer));

        $unauthorized = new TestUser;
        $unauthorized->assignedPermissions = ['delete_users'];
        $this->assertFalse(AccessResolver::canAccessItem($overrideAny, $unauthorized));

        $overrideAll = [
            'is_visible' => true,
            'permissions' => ['view_users', 'edit_users'],
            'permissions_condition' => 'all',
        ];

        $this->assertFalse(AccessResolver::canAccessItem($overrideAll, $viewer));

        $editor = new TestUser;
        $editor->assignedPermissions = ['view_users', 'edit_users'];
        $this->assertTrue(AccessResolver::canAccessItem($overrideAll, $editor));
    }

    public function test_access_resolver_requires_both_roles_and_permissions_when_both_set(): void
    {
        $override = [
            'is_visible' => true,
            'roles' => ['admin'],
            'permissions' => ['view_users'],
        ];

        $user = new TestUser;
        $user->assignedRoles = ['admin'];
        $user->assignedPermissions = [];
        $this->assertFalse(AccessResolver::canAccessItem($override, $user));

        $user->assignedPermissions = ['view_users'];
        $this->assertTrue(AccessResolver::canAccessItem($override, $user));
    }

    public function test_navigation_manager_hides_item_when_user_lacks_role(): void
    {
        ResourceSetting::query()->create([
            'panel_id' => 'admin',
            'resource_class' => self::USERS,
            'default_label' => 'Users',
            'is_visible' => true,
            'roles' => ['super_admin'],
        ]);

        $item = NavigationItem::make('Users')->url('http://localhost/admin/users');

        $manager = new class extends ManagedNavigationManager
        {
            public function applyForTest(NavigationItem $item, array $override): void
            {
                $this->applyOverride($item, $override);
            }
        };

        $unauthorizedUser = new TestUser;
        $unauthorizedUser->assignedRoles = ['manager'];
        $this->actingAs($unauthorizedUser);

        $overrides = OverrideRepository::forPanel('admin');
        $manager->applyForTest($item, $overrides[self::USERS]);

        $this->assertTrue($item->isHidden());
    }

    public function test_navigation_manager_shows_item_when_user_has_role(): void
    {
        ResourceSetting::query()->create([
            'panel_id' => 'admin',
            'resource_class' => self::USERS,
            'default_label' => 'Users',
            'is_visible' => true,
            'roles' => ['manager'],
        ]);

        $item = NavigationItem::make('Users')->url('http://localhost/admin/users');

        $manager = new class extends ManagedNavigationManager
        {
            public function applyForTest(NavigationItem $item, array $override): void
            {
                $this->applyOverride($item, $override);
            }
        };

        $authorizedUser = new TestUser;
        $authorizedUser->assignedRoles = ['manager'];
        $this->actingAs($authorizedUser);

        $overrides = OverrideRepository::forPanel('admin');
        $manager->applyForTest($item, $overrides[self::USERS]);

        $this->assertFalse($item->isHidden());
    }

    public function test_profile_resolver_resolves_role_specific_published_profile_before_default(): void
    {
        $defaultProfile = ProfileManager::ensureDefault('admin');
        ProfileManager::publish($defaultProfile);

        // Create manager profile assigned to role 'manager'
        $managerProfile = NavigationProfile::query()->create([
            'panel_id' => 'admin',
            'name' => 'Manager Layout',
            'slug' => 'manager-layout',
            'status' => 'draft',
            'is_default' => false,
            'roles' => ['manager'],
        ]);
        ProfileManager::publish($managerProfile);

        $this->assertTrue((bool) $defaultProfile->fresh()->is_default);
        $this->assertFalse((bool) $managerProfile->fresh()->is_default);

        // Guest or non-manager gets default profile
        $guestResolved = ProfileResolver::resolve(Panel::make()->id('admin'), null);
        $this->assertSame($defaultProfile->getKey(), $guestResolved?->getKey());

        // Manager user gets manager profile
        $managerUser = new TestUser;
        $managerUser->assignedRoles = ['manager'];
        $managerResolved = ProfileResolver::resolve(Panel::make()->id('admin'), $managerUser);
        $this->assertSame($managerProfile->getKey(), $managerResolved?->getKey());

        // Other role gets default profile
        $otherUser = new TestUser;
        $otherUser->assignedRoles = ['sales'];
        $otherResolved = ProfileResolver::resolve(Panel::make()->id('admin'), $otherUser);
        $this->assertSame($defaultProfile->getKey(), $otherResolved?->getKey());
    }

    public function test_role_targeting_is_versioned_and_rollback_restores_it(): void
    {
        $defaultProfile = ProfileManager::ensureDefault('admin');
        ProfileManager::publish($defaultProfile);
        $profile = ProfileManager::create('admin', 'Specialists', $defaultProfile);

        $supportVersion = ProfileManager::publish($profile, roles: ['support']);
        $this->assertSame(['support'], $supportVersion->roles);
        $this->assertSame(['support'], $profile->fresh()->roles);

        $accountantVersion = ProfileManager::publish($profile->fresh(), roles: ['accountant']);
        $this->assertSame(['accountant'], $accountantVersion->roles);

        $rollback = ProfileManager::rollback($profile->fresh(), $supportVersion->getKey());
        $this->assertSame(['support'], $rollback->roles);
        $this->assertSame(['support'], $profile->fresh()->roles);
        $this->assertTrue((bool) $defaultProfile->fresh()->is_default);

        $support = new TestUser;
        $support->assignedRoles = ['support'];
        $this->assertSame($profile->getKey(), ProfileResolver::resolve(Panel::make()->id('admin'), $support)?->getKey());

        $accountant = new TestUser;
        $accountant->assignedRoles = ['accountant'];
        $this->assertSame($defaultProfile->getKey(), ProfileResolver::resolve(Panel::make()->id('admin'), $accountant)?->getKey());
    }

    public function test_role_targeted_profile_requires_a_published_unrestricted_fallback(): void
    {
        $defaultProfile = ProfileManager::ensureDefault('admin');
        $profile = ProfileManager::create('admin', 'Support', $defaultProfile);

        try {
            ProfileManager::publish($profile, roles: ['support']);
            $this->fail('Expected publishing without a fallback to fail.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('unrestricted profile', $exception->getMessage());
        }

        $this->assertSame(0, $profile->versions()->count());
        $this->assertSame('draft', $profile->fresh()->status);
    }

    public function test_targeting_the_current_default_promotes_an_unrestricted_fallback(): void
    {
        $defaultProfile = ProfileManager::ensureDefault('admin');
        ProfileManager::publish($defaultProfile);
        $profile = ProfileManager::create('admin', 'Support', $defaultProfile);
        $unrestrictedVersion = ProfileManager::publish($profile);

        $this->assertTrue((bool) $profile->fresh()->is_default);
        $this->assertFalse((bool) $defaultProfile->fresh()->is_default);

        ProfileManager::publish($profile->fresh(), roles: ['support']);
        $this->assertFalse((bool) $profile->fresh()->is_default);
        $this->assertTrue((bool) $defaultProfile->fresh()->is_default);

        ProfileManager::rollback($profile->fresh(), $unrestrictedVersion->getKey());
        $this->assertNull($profile->fresh()->roles);
        $this->assertTrue((bool) $profile->fresh()->is_default);
        $this->assertFalse((bool) $defaultProfile->fresh()->is_default);
    }

    public function test_legacy_targeted_default_is_never_served_to_guests(): void
    {
        $profile = NavigationProfile::query()->create([
            'panel_id' => 'admin',
            'name' => 'Manager',
            'slug' => 'manager',
            'status' => 'published',
            'is_default' => true,
            'roles' => ['manager'],
        ]);
        $version = $profile->versions()->create([
            'version' => 1,
            'snapshot' => [],
            'roles' => ['manager'],
            'published_at' => now(),
        ]);
        $profile->forceFill(['published_version_id' => $version->getKey()])->save();

        $this->assertNull(ProfileResolver::resolve(Panel::make()->id('admin'), null));

        $manager = new TestUser;
        $manager->assignedRoles = ['manager'];
        $this->assertSame($profile->getKey(), ProfileResolver::resolve(Panel::make()->id('admin'), $manager)?->getKey());
    }

    public function test_invalid_role_input_cannot_turn_a_targeted_profile_into_the_default(): void
    {
        $defaultProfile = ProfileManager::ensureDefault('admin');
        ProfileManager::publish($defaultProfile);
        $profile = ProfileManager::create('admin', 'Support', $defaultProfile);

        $this->expectException(\InvalidArgumentException::class);

        try {
            ProfileManager::publish($profile, roles: ['']);
        } finally {
            $this->assertSame(0, $profile->versions()->count());
            $this->assertTrue((bool) $defaultProfile->fresh()->is_default);
        }
    }

    public function test_custom_role_and_permission_resolvers_via_plugin_hooks(): void
    {
        $panel = Panel::make()->id('admin');
        $plugin = FilamentResourceManagerPlugin::make()
            ->resolveUserRolesUsing(fn ($u): array => ['custom_admin'])
            ->resolveUserPermissionsUsing(fn ($u): array => ['custom_view']);

        $plugin->register($panel);

        $user = new TestUser;

        $this->assertSame(['custom_admin'], AccessResolver::getUserRoles($user));
        $this->assertTrue(AccessResolver::userHasRoles($user, ['custom_admin']));
        $this->assertFalse(AccessResolver::userHasRoles($user, ['other_role']));

        $this->assertTrue(AccessResolver::userHasPermissions($user, ['custom_view']));
        $this->assertFalse(AccessResolver::userHasPermissions($user, ['other_perm']));
    }

    public function test_get_available_roles_and_permissions_from_config(): void
    {
        config()->set('filament-resource-manager.access_control.roles', [
            'admin' => 'Administrator',
            'manager',
        ]);
        config()->set('filament-resource-manager.access_control.permissions', [
            'view_orders' => 'View Orders',
            'delete_orders',
        ]);

        $roles = AccessResolver::getAvailableRoles();
        $this->assertArrayHasKey('admin', $roles);
        $this->assertSame('Administrator', $roles['admin']);
        $this->assertArrayHasKey('manager', $roles);

        $permissions = AccessResolver::getAvailablePermissions();
        $this->assertArrayHasKey('view_orders', $permissions);
        $this->assertSame('View Orders', $permissions['view_orders']);
        $this->assertArrayHasKey('delete_orders', $permissions);
    }
}
