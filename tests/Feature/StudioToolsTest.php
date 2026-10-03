<?php

namespace MahmoudSehsah\FilamentResourceManager\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Mechanisms\DataStore;
use MahmoudSehsah\FilamentResourceManager\Filament\Concerns\ManagesNavigationStudio;
use MahmoudSehsah\FilamentResourceManager\Filament\V4\Pages\NavigationStudio;
use MahmoudSehsah\FilamentResourceManager\Models\NavigationProfile;
use MahmoudSehsah\FilamentResourceManager\Support\OverrideRepository;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileComparison;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileManager;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileTransfer;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceSynchroniser;
use MahmoudSehsah\FilamentResourceManager\Support\RolePreview;
use MahmoudSehsah\FilamentResourceManager\Tests\TestCase;

class StudioToolsTest extends TestCase
{
    protected Panel $panel;

    protected function setUp(): void
    {
        parent::setUp();
        // Keep Livewire component state shared in the isolated test container.
        app()->singleton(DataStore::class);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
        $this->panel = Panel::make()->id('admin')->resources([TransferUsers::class, TransferOrders::class]);
        Filament::setCurrentPanel($this->panel);
        config()->set('filament-resource-manager.access_control.roles', ['manager', 'finance']);
        config()->set('filament-resource-manager.access_control.permissions', ['view_orders', 'edit_orders']);
        OverrideRepository::resetState();
        ResourceSynchroniser::sync($this->panel);
    }

    public function test_livewire_download_review_import_role_controls_and_comparison(): void
    {
        $profile = ProfileManager::ensureDefault('admin');
        $version = ProfileManager::publish($profile);
        $profile->items()->where('resource_class', TransferUsers::class)->first()->update(['label' => 'Team']);
        $json = ProfileTransfer::export($profile->fresh());

        Livewire::test(StudioToolsHarness::class)
            ->assertSee('Version comparison')
            ->assertSee('Team')
            ->call('exportProfile')
            ->assertFileDownloaded('navigation-profile-'.$profile->id.'.json')
            ->set('compareFrom', '')
            ->set('compareTo', (string) $version->id)
            ->assertSee('Team')
            ->set('previewEnabled', true)
            ->set('previewRoles', ['manager'])
            ->set('previewPermissions', ['view_orders'])
            ->assertSee('Simulated permissions')
            ->set('importJson', $json)
            ->call('reviewImport')
            ->assertSee('2 resources validated')
            ->set('importName', 'From Livewire')
            ->call('importProfile')
            ->assertSet('importJson', '');

        $this->assertTrue(NavigationProfile::query()->where('name', 'From Livewire')->whereNull('published_version_id')->exists());
    }

    public function test_import_review_hash_cannot_be_forged_through_livewire(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(StudioToolsHarness::class)->set('reviewedImportHash', 'forged');
    }

    public function test_export_import_round_trip_is_a_new_unpublished_profile(): void
    {
        $profile = ProfileManager::ensureDefault('admin');
        $profile->items()->where('resource_class', TransferUsers::class)->first()->update([
            'label' => 'فريق العمل', 'icon_type' => 'code', 'icon' => 'heroicon-o-users',
            'roles' => ['manager'], 'permissions' => ['view_orders'], 'roles_condition' => 'all',
            'navigation_group' => 'People', 'navigation_group_overridden' => true,
        ]);
        $profile->items()->where('resource_class', TransferOrders::class)->first()->update([
            'parent_resource_class' => TransferUsers::class, 'roles' => ['manager'],
        ]);
        $published = ProfileManager::publish($profile);
        $json = ProfileTransfer::export($profile->fresh());
        $payload = json_decode($json, true);
        $this->assertArrayNotHasKey('panel_id', $payload);
        $this->assertArrayNotHasKey('profile_id', $payload['items'][0]);
        $this->assertArrayNotHasKey('default_label', $payload['items'][0]);

        $imported = ProfileTransfer::import($json, $this->panel, 'Imported');
        $this->assertNotSame($profile->id, $imported->id);
        $this->assertNull($imported->published_version_id);
        $this->assertFalse($imported->is_default);
        $this->assertSame(0, $imported->versions()->count());
        $this->assertSame($published->id, $profile->fresh()->published_version_id);
        $this->assertSame($published->snapshot, $published->fresh()->snapshot);
        $expected = json_decode($json, true)['items'];
        $actual = json_decode(ProfileTransfer::export($imported), true)['items'];
        $this->assertEquals(collect($expected)->keyBy('resource_class')->all(), collect($actual)->keyBy('resource_class')->all());
    }

    public function test_import_does_not_sync_or_auto_publish_other_profiles(): void
    {
        $profile = ProfileManager::ensureDefault('admin');
        $version = ProfileManager::publish($profile);
        $profile->items()->first()->update(['default_label' => 'Stale default']);
        $before = ProfileManager::snapshot($profile);
        $json = ProfileTransfer::export($profile);
        ProfileTransfer::import($json, $this->panel, 'Isolated');
        $this->assertSame($before, ProfileManager::snapshot($profile));
        $this->assertSame(0, ProfileManager::publishPending());
        $this->assertSame($version->id, $profile->fresh()->published_version_id);
    }

    public function test_import_rejects_unknown_resources_roles_and_mass_assignment(): void
    {
        $profile = ProfileManager::ensureDefault('admin');
        $original = json_decode(ProfileTransfer::export($profile), true);
        foreach (['resource_class' => 'UnknownResource', 'roles' => ['unknown-role'], 'profile_id' => 99, 'is_visible' => 'false'] as $field => $value) {
            $data = $original;
            $data['items'][0][$field] = $value;
            try {
                ProfileTransfer::import(json_encode($data), $this->panel, 'Bad');
                $this->fail('Invalid input was accepted: '.$field);
            } catch (ValidationException) {
                $this->assertSame(1, NavigationProfile::query()->count());
            }
        }
    }

    public function test_import_rejects_invalid_json_size_version_and_cycles_without_writes(): void
    {
        $profile = ProfileManager::ensureDefault('admin');
        $data = json_decode(ProfileTransfer::export($profile), true);
        $data['items'][0]['parent_resource_class'] = $data['items'][1]['resource_class'];
        $data['items'][1]['parent_resource_class'] = $data['items'][0]['resource_class'];
        foreach (['{broken', str_repeat(' ', ProfileTransfer::MAX_BYTES + 1), json_encode($data)] as $json) {
            try {
                ProfileTransfer::import($json, $this->panel, 'Invalid');
                $this->fail('Invalid input was accepted');
            } catch (\InvalidArgumentException) {
                $this->assertSame(1, NavigationProfile::query()->count());
            }
        }
        $data['version'] = 999;
        $this->expectException(ValidationException::class);
        ProfileTransfer::prepare(json_encode($data), $this->panel);
    }

    public function test_transfer_sanitizes_svg_before_preview_and_preserves_image_references(): void
    {
        $profile = ProfileManager::ensureDefault('admin');
        $data = json_decode(ProfileTransfer::export($profile), true);
        $data['items'][0]['icon_type'] = 'svg';
        $data['items'][0]['icon_svg'] = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0h1"/></svg>';
        $data['items'][1]['icon_type'] = 'image';
        $data['items'][1]['icon_image'] = 'navigation-icons/team.png';
        $prepared = ProfileTransfer::prepare(json_encode($data), $this->panel);
        $this->assertStringNotContainsString('script', $prepared['items'][0]['icon_svg']);
        $this->assertSame('navigation-icons/team.png', $prepared['items'][1]['icon_image']);
    }

    public function test_import_requires_a_fresh_review_and_scopes_the_current_profile_to_the_panel(): void
    {
        $profile = ProfileManager::ensureDefault('admin');
        $page = new NavigationStudio;
        $page->profileId = $profile->id;
        $page->importJson = ProfileTransfer::export($profile);
        $page->importName = 'Reviewed';
        $page->importProfile();
        $this->assertSame(1, NavigationProfile::query()->count());
        $page->reviewImport();
        $page->importJson .= ' ';
        $page->importProfile();
        $this->assertSame(1, NavigationProfile::query()->count());
        $page->reviewImport();
        $page->importProfile();
        $this->assertSame(2, NavigationProfile::query()->count());
        $this->assertSame('', $page->importJson);
        $other = ProfileManager::create('other', 'Other');
        $page->profileId = $other->id;
        $this->assertNull($page->exportProfile());
    }

    public function test_comparison_handles_fields_audience_additions_removals_and_immutable_history(): void
    {
        $profile = ProfileManager::ensureDefault('admin');
        $first = ProfileManager::publish($profile);
        $profile->items()->where('resource_class', TransferUsers::class)->first()->update(['label' => 'Team', 'is_visible' => false]);
        $second = ProfileManager::publish($profile);
        $changes = ProfileComparison::compare($profile->fresh(), $first->id, $second->id);
        $this->assertSame(['label', 'is_visible'], array_column($changes, 'field'));
        $this->assertSame('Team', $changes[0]['after']);
        $this->assertSame([], ProfileComparison::compare($profile->fresh(), $second->id, null));
        $profile->items()->where('resource_class', TransferOrders::class)->delete();
        $profile->update(['roles' => ['manager']]);
        $changes = ProfileComparison::compare($profile->fresh(), $second->id, null);
        $this->assertSame('roles', $changes[0]['field']);
        $this->assertSame('removed', $changes[1]['type']);
        $this->assertSame('added', ProfileComparison::compare($profile->fresh(), null, $second->id)[1]['type']);
        $this->assertCount(2, $first->fresh()->snapshot);
    }

    public function test_comparison_rejects_a_version_from_another_profile(): void
    {
        $profile = ProfileManager::ensureDefault('admin');
        $other = ProfileManager::create('other', 'Other');
        $version = ProfileManager::publish($other);
        $this->expectException(ModelNotFoundException::class);
        ProfileComparison::compare($profile, $version->id, null);
    }

    public function test_role_preview_combines_audience_visibility_roles_and_permissions_without_mutation(): void
    {
        config()->set('filament-resource-manager.access_control.role_permissions', ['manager' => ['view_orders']]);
        $permissions = RolePreview::permissions(['manager']);
        $this->assertSame(['view_orders'], $permissions);
        $items = [
            ['resource_class' => 'users', 'roles' => ['manager', 'finance'], 'roles_condition' => 'all'],
            ['resource_class' => 'orders', 'permissions' => ['view_orders', 'edit_orders'], 'permissions_condition' => 'any'],
            ['resource_class' => 'hidden', 'is_visible' => false],
        ];
        $reasons = RolePreview::reasons($items, ['manager'], $permissions);
        $this->assertSame(['roles'], $reasons['users']);
        $this->assertSame([], $reasons['orders']);
        $this->assertSame(['hidden'], $reasons['hidden']);
        $this->assertSame(['audience'], RolePreview::reasons([$items[1]], ['manager'], $permissions, ['finance'])['orders']);
        $this->assertSame(['permissions'], RolePreview::reasons([$items[1]], [], [])['orders']);
        $this->assertSame(1, NavigationProfile::query()->count());
    }
}

class TransferUsers extends Resource
{
    public static function getNavigationLabel(): string
    {
        return 'Users';
    }
}

class TransferOrders extends Resource
{
    public static function getNavigationLabel(): string
    {
        return 'Orders';
    }
}

class StudioToolsHarness extends Component
{
    use ManagesNavigationStudio;

    public function render()
    {
        return view()->file(__DIR__.'/../Fixtures/studio-tools.blade.php', $this->getViewData());
    }
}
