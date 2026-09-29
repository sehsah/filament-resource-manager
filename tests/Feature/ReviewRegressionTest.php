<?php

namespace MahmoudSehsah\FilamentResourceManager\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use MahmoudSehsah\FilamentResourceManager\Models\ResourceSetting;
use MahmoudSehsah\FilamentResourceManager\Support\OverrideRepository;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileManager;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileResolver;
use MahmoudSehsah\FilamentResourceManager\Support\SettingsReset;
use MahmoudSehsah\FilamentResourceManager\Tests\TestCase;

class ReviewRegressionTest extends TestCase
{
    private const USERS = 'App\\Filament\\Resources\\UserResource';

    private const POSTS = 'App\\Filament\\Resources\\PostResource';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Panel::make()->id('admin'));
        ProfileResolver::flush();
        OverrideRepository::flush();
    }

    public function test_seeding_the_default_profile_keeps_access_control(): void
    {
        $this->restrictedSetting(self::USERS);

        $item = ProfileManager::ensureDefault('admin')->items()->first();

        $this->assertSame(['admin'], $item->roles);
        $this->assertSame(['view users'], $item->permissions);
        $this->assertSame('all', $item->roles_condition);
        $this->assertSame('all', $item->permissions_condition);
    }

    public function test_publishing_a_seeded_profile_keeps_items_restricted(): void
    {
        $this->restrictedSetting(self::USERS);

        ProfileManager::publish(ProfileManager::ensureDefault('admin'));
        ProfileResolver::flush();
        OverrideRepository::flush();

        $override = OverrideRepository::forPanel('admin')[self::USERS];

        $this->assertSame(['admin'], $override['roles']);
        $this->assertSame(['view users'], $override['permissions']);
    }

    public function test_a_resource_added_later_keeps_access_control_in_existing_profiles(): void
    {
        ProfileManager::ensureDefault('admin');
        $this->restrictedSetting(self::POSTS);

        ProfileManager::syncPanel('admin', [self::POSTS]);

        $item = ProfileManager::ensureDefault('admin')->items()
            ->where('resource_class', self::POSTS)
            ->first();

        $this->assertSame(['admin'], $item->roles);
        $this->assertSame(['view users'], $item->permissions);
    }

    public function test_reset_all_reaches_the_default_profile_draft(): void
    {
        $setting = ResourceSetting::query()->create([
            'panel_id' => 'admin',
            'resource_class' => self::USERS,
            'label' => 'People',
            'badge' => 'NEW',
            'is_visible' => false,
            'roles' => ['admin'],
        ]);
        $profile = ProfileManager::ensureDefault('admin');
        ProfileManager::publish($profile);

        $this->assertSame('People', $profile->items()->first()->label);

        $count = SettingsReset::all(ResourceSetting::query()->where('panel_id', 'admin'));

        $this->assertSame(1, $count);

        $setting->refresh();
        $this->assertNull($setting->label);
        $this->assertTrue($setting->is_visible);
        $this->assertSame(['admin'], $setting->roles, 'Reset must not widen access.');

        $item = $profile->items()->first();
        $this->assertNull($item->label);
        $this->assertNull($item->badge);
        $this->assertTrue($item->is_visible);
        $this->assertSame(['admin'], $item->roles);
        $this->assertSame('draft', $profile->fresh()->status);
    }

    public function test_long_lived_workers_do_not_keep_a_stale_profile(): void
    {
        ResourceSetting::query()->create([
            'panel_id' => 'admin',
            'resource_class' => self::USERS,
            'label' => 'Before',
        ]);
        $profile = ProfileManager::ensureDefault('admin');
        ProfileManager::publish($profile);
        ProfileResolver::flush();
        OverrideRepository::flush();

        $this->assertSame('Before', OverrideRepository::forPanel('admin')[self::USERS]['label']);

        // Another worker publishes: rows change and the shared cache is
        // cleared, but this process's in-memory memo is not.
        $version = $profile->versions()->create([
            'version' => 2,
            'snapshot' => [['resource_class' => self::USERS, 'label' => 'After']],
            'published_at' => now(),
        ]);
        DB::table($profile->getTable())->where('id', $profile->getKey())
            ->update(['published_version_id' => $version->getKey()]);
        cache()->flush();

        $this->assertSame('Before', OverrideRepository::forPanel('admin')[self::USERS]['label']);

        event(new JobProcessing('sync', new FakeJob));

        $this->assertSame('After', OverrideRepository::forPanel('admin')[self::USERS]['label']);
    }

    public function test_octane_request_event_resets_state(): void
    {
        $this->assertTrue(
            $this->app['events']->hasListeners('Laravel\\Octane\\Events\\RequestReceived'),
        );
    }

    private function restrictedSetting(string $resource): void
    {
        // Written without events so nothing is mirrored into a profile yet,
        // matching an install that configured access before opening the Studio.
        ResourceSetting::withoutEvents(fn () => ResourceSetting::query()->create([
            'panel_id' => 'admin',
            'resource_class' => $resource,
            'roles' => ['admin'],
            'permissions' => ['view users'],
            'roles_condition' => 'all',
            'permissions_condition' => 'all',
        ]));
    }
}

class FakeJob extends SyncJob
{
    public function __construct()
    {
        //
    }
}
