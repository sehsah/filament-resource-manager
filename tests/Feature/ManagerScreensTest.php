<?php

namespace MahmoudSehsah\FilamentResourceManager\Tests\Feature;

use MahmoudSehsah\FilamentResourceManager\Filament\V4\Pages\NavigationStudio;
use MahmoudSehsah\FilamentResourceManager\Filament\V4\ResourceSettingResource;
use MahmoudSehsah\FilamentResourceManager\Support\FilamentVersion;
use MahmoudSehsah\FilamentResourceManager\Tests\TestCase;

class ManagerScreensTest extends TestCase
{
    /**
     * The studio pages used to call authorizeAccess(), which
     * Filament\Resources\Pages\Page does not declare on any major - so
     * opening the page was a fatal "call to undefined method". Access comes
     * from the resource's canAccess(), through Filament's own
     * CanAuthorizeResourceAccess hooks, instead.
     *
     * The v3 page is checked as source text rather than as a class: its
     * resource declares the v3 form() signature, which cannot load against a
     * v4 or v5 install.
     */
    public function test_studio_pages_do_not_call_a_method_filament_does_not_declare(): void
    {
        foreach (['V3', 'V4'] as $variant) {
            $source = file_get_contents(
                __DIR__."/../../src/Filament/{$variant}/Pages/NavigationStudio.php",
            );

            $this->assertIsString($source);
            $this->assertStringNotContainsString('authorizeAccess', $source);
        }

        $this->assertFalse(method_exists(NavigationStudio::class, 'authorizeAccess'));
        $this->assertTrue(method_exists(NavigationStudio::class, 'mountCanAuthorizeResourceAccess'));
    }

    /**
     * v3 keeps these custom properties as an "r, g, b" triplet; v4 and v5
     * store a complete oklch() colour, which renders nothing if it is wrapped
     * in rgb().
     */
    public function test_badge_colour_swatches_use_the_colour_format_of_the_installed_major(): void
    {
        try {
            FilamentVersion::fake(3);
            $v3 = ResourceSettingResource::badgeColorOptions();

            FilamentVersion::fake(4);
            $v4 = ResourceSettingResource::badgeColorOptions();
        } finally {
            FilamentVersion::fake(null);
        }

        $this->assertStringContainsString('background-color:rgb(var(--success-500', $v3['success']);

        $this->assertStringContainsString('background-color:var(--success-500', $v4['success']);
        $this->assertStringNotContainsString('rgb(var(', $v4['success']);
    }

    /**
     * Two 255-character columns in one unique index need 2040 bytes under
     * utf8mb4, over the limit on MySQL and MariaDB configurations still using
     * a 767-byte index prefix. 191 is the conventional ceiling.
     *
     * Asserted against the migrations rather than the created schema: the test
     * suite runs on SQLite, which accepts a length and then ignores it.
     */
    public function test_columns_in_unique_indexes_declare_a_bounded_length(): void
    {
        $settings = '2026_09_13_152922_create_filament_resource_settings_table.php';
        $profiles = '2026_09_14_010000_create_filament_navigation_profile_tables.php';

        foreach ([
            $settings => ['panel_id', 'resource_class'],
            $profiles => ['panel_id', 'slug', 'resource_class'],
        ] as $migration => $columns) {
            $source = file_get_contents(__DIR__.'/../../database/migrations/'.$migration);

            $this->assertIsString($source);

            foreach ($columns as $column) {
                $this->assertStringContainsString(
                    "string('{$column}', 191)",
                    $source,
                    "{$column} sits in a unique index and should be bounded at 191 characters",
                );
            }
        }
    }
}
