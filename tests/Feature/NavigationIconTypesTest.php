<?php

namespace MahmoudSehsah\FilamentResourceManager\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use MahmoudSehsah\FilamentResourceManager\Models\ResourceSetting;
use MahmoudSehsah\FilamentResourceManager\Navigation\ManagedNavigationManager;
use MahmoudSehsah\FilamentResourceManager\Support\NavigationIcon;
use MahmoudSehsah\FilamentResourceManager\Support\OverrideRepository;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileManager;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileResolver;
use MahmoudSehsah\FilamentResourceManager\Tests\TestCase;

/**
 * A navigation icon can be a picked icon, a typed icon name, raw SVG markup or
 * an uploaded image, and each has to reach the sidebar - through the legacy
 * settings table and through a published profile alike.
 */
class NavigationIconTypesTest extends TestCase
{
    private const USERS = 'App\\Filament\\Resources\\UserResource';

    private const SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 12h16"/></svg>';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Panel::make()->id('admin'));
        NavigationIcon::flush();
    }

    public function test_rows_without_a_type_keep_behaving_as_icon_names(): void
    {
        $this->assertSame('heroicon-o-users', NavigationIcon::resolve(['icon' => 'heroicon-o-users']));
        $this->assertNull(NavigationIcon::resolve(['icon' => '']));
    }

    public function test_a_typed_icon_name_is_used_as_is(): void
    {
        $this->assertSame('tabler-home', NavigationIcon::resolve([
            'icon_type' => 'code',
            'icon' => ' tabler-home ',
        ]));
    }

    public function test_svg_markup_is_rendered_inline(): void
    {
        $icon = NavigationIcon::resolve(['icon_type' => 'svg', 'icon_svg' => self::SVG]);

        $this->assertInstanceOf(Htmlable::class, $icon);
        $this->assertStringContainsString('<path d="M4 12h16"', $icon->toHtml());
        $this->assertStringContainsString('width="100%"', $icon->toHtml());
    }

    public function test_svg_is_stripped_of_anything_executable(): void
    {
        $svg = NavigationIcon::sanitizeSvg(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24" onload="alert(1)">'
            .'<script>alert(2)</script>'
            .'<foreignObject><div>x</div></foreignObject>'
            .'<defs><linearGradient id="g"><stop offset="0"/></linearGradient></defs>'
            .'<a href="javascript:alert(3)"><path d="M0 0"/></a>'
            .'<use xlink:href="https://evil.test/x.svg#a"/>'
            .'<use href="#g"/>'
            .'<rect fill="url(#g)" style="fill:url(https://evil.test)" width="4" height="4" onclick="alert(4)"/>'
            .'</svg>'
        );

        $this->assertNotNull($svg);
        $this->assertStringNotContainsString('alert', $svg);
        $this->assertStringNotContainsString('script', $svg);
        $this->assertStringNotContainsString('foreignObject', $svg);
        $this->assertStringNotContainsString('evil.test', $svg);
        $this->assertStringNotContainsString('<a', $svg);
        $this->assertStringContainsString('fill="url(#g)"', $svg);
        $this->assertStringContainsString('href="#g"', $svg);
    }

    public function test_invalid_or_hostile_svg_is_rejected(): void
    {
        $this->assertNull(NavigationIcon::sanitizeSvg('<div>not svg</div>'));
        $this->assertNull(NavigationIcon::sanitizeSvg('<svg><path></svg>'));
        $this->assertNull(NavigationIcon::sanitizeSvg(
            '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg>&x;</svg>'
        ));
        $this->assertNull(NavigationIcon::resolve(['icon_type' => 'svg', 'icon_svg' => '<b>nope</b>']));
    }

    public function test_an_uploaded_image_resolves_to_its_public_url(): void
    {
        config()->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => sys_get_temp_dir(),
            'url' => 'http://localhost/storage',
            'visibility' => 'public',
        ]);

        $this->assertSame(
            'http://localhost/storage/navigation-icons/users.png',
            NavigationIcon::resolve(['icon_type' => 'image', 'icon_image' => 'navigation-icons/users.png']),
        );
        $this->assertSame(
            'https://cdn.test/users.png',
            NavigationIcon::resolve(['icon_type' => 'image', 'icon_image' => 'https://cdn.test/users.png']),
        );
    }

    public function test_saving_clears_the_payloads_of_other_types(): void
    {
        $setting = $this->setting([
            'icon_type' => 'svg',
            'icon' => 'heroicon-o-users',
            'icon_svg' => self::SVG,
        ]);

        $this->assertNull($setting->fresh()->icon);
        $this->assertSame(self::SVG, $setting->fresh()->icon_svg);

        $setting->fill(['icon_type' => 'icon', 'icon' => 'heroicon-o-home'])->save();

        $this->assertNull($setting->fresh()->icon_svg);
        $this->assertSame('heroicon-o-home', $setting->fresh()->icon);
    }

    public function test_an_icon_type_alone_is_not_an_override(): void
    {
        $this->assertTrue($this->setting(['icon_type' => 'svg'])->fresh()->isPassthrough());
        $this->assertFalse($this->setting([
            'resource_class' => 'App\\Other',
            'icon_type' => 'svg',
            'icon_svg' => self::SVG,
        ])->fresh()->isPassthrough());
    }

    public function test_an_svg_icon_reaches_the_navigation_item(): void
    {
        $this->setting(['icon_type' => 'svg', 'icon_svg' => self::SVG, 'active_icon' => 'heroicon-s-users']);

        $item = $this->applyTo(OverrideRepository::forPanel('admin')[self::USERS]);

        $this->assertInstanceOf(Htmlable::class, $item->getIcon());
        $this->assertStringContainsString('M4 12h16', $item->getIcon()->toHtml());
        $this->assertSame('heroicon-s-users', $item->getActiveIcon());
    }

    public function test_an_svg_icon_survives_publishing_a_profile(): void
    {
        $this->setting(['icon_type' => 'svg', 'icon_svg' => self::SVG]);

        $profile = ProfileManager::ensureDefault('admin');
        ProfileManager::publish($profile);

        ProfileResolver::flush();
        OverrideRepository::flush();
        $override = OverrideRepository::forPanel('admin')[self::USERS];

        $this->assertSame('svg', $override['icon_type']);
        $this->assertInstanceOf(Htmlable::class, $this->applyTo($override)->getIcon());

        // A later edit goes to the draft item too.
        ResourceSetting::query()->where('resource_class', self::USERS)->firstOrFail()
            ->fill(['icon_type' => 'code', 'icon' => 'tabler-home'])
            ->save();

        $item = $profile->fresh()->items()->where('resource_class', self::USERS)->firstOrFail();
        $this->assertSame('code', $item->icon_type);
        $this->assertSame('tabler-home', $item->icon);
        $this->assertNull($item->icon_svg);
    }

    /** @param  array<string, mixed>  $attributes */
    private function setting(array $attributes): ResourceSetting
    {
        return ResourceSetting::query()->create([
            'panel_id' => 'admin',
            'resource_class' => self::USERS,
            'default_label' => 'Users',
            'is_visible' => true,
            ...$attributes,
        ]);
    }

    /** @param  array<string, mixed>  $override */
    private function applyTo(array $override): NavigationItem
    {
        $item = NavigationItem::make('Users')->url('http://localhost/admin/users');

        $manager = new class extends ManagedNavigationManager
        {
            public function applyForTest(NavigationItem $item, array $override): void
            {
                $this->applyOverride($item, $override);
            }
        };

        $manager->applyForTest($item, $override);

        return $item;
    }
}
