<?php

namespace MahmoudSehsah\FilamentResourceManager\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Panel;
use MahmoudSehsah\FilamentResourceManager\Models\ResourceSetting;
use MahmoudSehsah\FilamentResourceManager\Support\OverrideRepository;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileManager;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileResolver;
use MahmoudSehsah\FilamentResourceManager\Support\SettingsReset;
use MahmoudSehsah\FilamentResourceManager\Tests\TestCase;

/**
 * Once a profile is published it serves navigation, so a resource edit used to
 * sit in the draft until someone published it from Navigation Studio. With
 * auto-publish on, the edit goes live by itself.
 */
class AutoPublishTest extends TestCase
{
    private const USERS = 'App\\Filament\\Resources\\UserResource';

    private const INVOICES = 'App\\Filament\\Resources\\InvoiceResource';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Panel::make()->id('admin'));
    }

    public function test_a_resource_edit_goes_live_without_publishing_by_hand(): void
    {
        $profile = $this->publishedProfile();

        $this->users()->fill(['label' => 'Members'])->save();
        $this->assertSame(1, ProfileManager::publishPending());

        $this->assertSame('Members', $this->overrides()[self::USERS]['label']);
        $this->assertSame(2, $profile->versions()->count());
        $this->assertSame('published', $profile->fresh()->status);
    }

    public function test_a_burst_of_saves_publishes_one_version(): void
    {
        $profile = $this->publishedProfile();

        SettingsReset::all(ResourceSetting::query());
        $this->users()->fill(['sort' => 5])->save();
        ResourceSetting::query()->where('resource_class', self::INVOICES)->firstOrFail()
            ->fill(['is_visible' => false])->save();

        ProfileManager::publishPending();

        $this->assertSame(2, $profile->versions()->count());
        $this->assertFalse($this->overrides()[self::INVOICES]['is_visible']);
    }

    public function test_it_can_be_turned_off(): void
    {
        config()->set('filament-resource-manager.profiles.auto_publish', false);
        $profile = $this->publishedProfile();

        $this->users()->fill(['label' => 'Members'])->save();

        $this->assertSame(0, ProfileManager::publishPending());
        $this->assertNull($this->overrides()[self::USERS]['label']);
        $this->assertSame('draft', $profile->fresh()->status);
    }

    public function test_an_unpublished_profile_is_never_published_automatically(): void
    {
        $this->seedResources();
        $profile = ProfileManager::ensureDefault('admin');

        $this->users()->fill(['label' => 'Members'])->save();

        $this->assertSame(0, ProfileManager::publishPending());
        $this->assertSame(0, $profile->versions()->count());
        // No profile governs yet, so the settings table serves the edit directly.
        $this->assertSame('Members', $this->overrides()[self::USERS]['label']);
    }

    public function test_opening_the_manager_does_not_publish(): void
    {
        $profile = $this->publishedProfile();

        foreach (ResourceSetting::query()->get() as $row) {
            $row->touch();
        }

        $this->assertSame(0, ProfileManager::publishPending());
        $this->assertSame(1, $profile->versions()->count());
    }

    private function publishedProfile()
    {
        $this->seedResources();
        $profile = ProfileManager::ensureDefault('admin');
        ProfileManager::publish($profile);
        ProfileManager::forgetPending();

        return $profile->fresh();
    }

    private function users(): ResourceSetting
    {
        return ResourceSetting::query()->where('resource_class', self::USERS)->firstOrFail();
    }

    /** @return array<string, array<string, mixed>> */
    private function overrides(): array
    {
        ProfileResolver::flush();
        OverrideRepository::flush();

        return OverrideRepository::forPanel('admin');
    }

    private function seedResources(): void
    {
        foreach ([self::USERS => 'Users', self::INVOICES => 'Invoices'] as $class => $label) {
            ResourceSetting::query()->create([
                'panel_id' => 'admin',
                'resource_class' => $class,
                'default_label' => $label,
                'is_visible' => true,
            ]);
        }
    }
}
