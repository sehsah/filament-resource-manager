<?php

namespace MahmoudSehsah\FilamentResourceManager\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Panel;
use MahmoudSehsah\FilamentResourceManager\Models\ResourceSetting;
use MahmoudSehsah\FilamentResourceManager\Support\OverrideRepository;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileManager;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileResolver;
use MahmoudSehsah\FilamentResourceManager\Tests\TestCase;

/**
 * What the navigation layer actually receives, end to end.
 *
 * OverrideRepository swallows throwables so a half-migrated database renders
 * navigation untouched - which also means a mistake inside it looks exactly
 * like "no overrides configured". Nothing else in the suite read overrides
 * back through forPanel() with a profile published, so a missing method there
 * went unnoticed and emptied every override on the panel.
 */
class SidebarReflectsStudioTest extends TestCase
{
    private const USERS = 'App\\Filament\\Resources\\UserResource';

    private const INVOICES = 'App\\Filament\\Resources\\InvoiceResource';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Panel::make()->id('admin'));
    }

    public function test_a_studio_layout_reaches_navigation_without_publishing(): void
    {
        $this->seedResources();
        $profile = ProfileManager::ensureDefault('admin');

        ProfileManager::saveLayout($profile, [
            $this->item(self::USERS, 'Operations'),
            $this->item(self::INVOICES, 'Operations', isVisible: false),
        ]);

        $overrides = $this->overrides();

        $this->assertSame('Operations', $overrides[self::USERS]['navigation_group'] ?? null);
        $this->assertTrue($overrides[self::USERS]['is_visible'] ?? false);
        $this->assertFalse($overrides[self::INVOICES]['is_visible'] ?? true);
    }

    public function test_a_published_profile_reaches_navigation(): void
    {
        $this->seedResources();
        $profile = ProfileManager::ensureDefault('admin');
        ProfileManager::publish($profile);

        ProfileManager::saveLayout($profile->fresh(), [
            $this->item(self::INVOICES, 'Finance'),
            $this->item(self::USERS, 'Finance', parent: self::INVOICES),
        ]);

        // A published version is immutable, so the draft is not live yet.
        $this->assertNull($this->overrides()[self::INVOICES]['navigation_group'] ?? null);

        ProfileManager::publish($profile->fresh());
        $overrides = $this->overrides();

        $this->assertNotSame([], $overrides, 'a published profile must still produce overrides');
        $this->assertSame('Finance', $overrides[self::INVOICES]['navigation_group'] ?? null);
        $this->assertSame(self::INVOICES, $overrides[self::USERS]['parent_resource_class'] ?? null);
        $this->assertSame(2, $overrides[self::USERS]['sort'] ?? null);
    }

    public function test_publishing_twice_is_not_served_from_a_stale_cache(): void
    {
        $this->seedResources();
        $profile = ProfileManager::ensureDefault('admin');

        ProfileManager::saveLayout($profile, [$this->item(self::USERS, 'First')]);
        ProfileManager::publish($profile->fresh());

        $this->assertSame('First', $this->overrides()[self::USERS]['navigation_group'] ?? null);

        ProfileManager::saveLayout($profile->fresh(), [$this->item(self::USERS, 'Second')]);
        ProfileManager::publish($profile->fresh());

        $this->assertSame('Second', $this->overrides()[self::USERS]['navigation_group'] ?? null);
    }

    /**
     * The studio and the list page synchronise on every mount, which saves
     * settings rows. Those mirror into the draft, so a saved layout has to
     * survive the round trip.
     */
    public function test_a_synchronise_does_not_undo_a_saved_layout(): void
    {
        $this->seedResources();
        $profile = ProfileManager::ensureDefault('admin');

        ProfileManager::saveLayout($profile, [
            $this->item(self::USERS, 'Operations', isVisible: false),
            $this->item(self::INVOICES, null),
        ]);

        foreach (ResourceSetting::query()->get() as $row) {
            $row->touch();
        }

        $users = $profile->fresh()->items()->where('resource_class', self::USERS)->firstOrFail();
        $invoices = $profile->fresh()->items()->where('resource_class', self::INVOICES)->firstOrFail();

        $this->assertSame('Operations', $users->navigation_group);
        $this->assertFalse((bool) $users->is_visible);

        // Dropped in the Ungrouped bucket: no group, not "use the default".
        $this->assertNull($invoices->navigation_group);
        $this->assertTrue((bool) $invoices->navigation_group_overridden);
    }

    /**
     * @return array<string, mixed>
     */
    private function item(
        string $resource,
        ?string $group,
        ?string $parent = null,
        bool $isVisible = true,
    ): array {
        return [
            'resource_class' => $resource,
            'navigation_group' => $group,
            'parent_resource_class' => $parent,
            'is_visible' => $isVisible,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function overrides(): array
    {
        ProfileResolver::flush();
        OverrideRepository::flush();

        return OverrideRepository::forPanel('admin');
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
