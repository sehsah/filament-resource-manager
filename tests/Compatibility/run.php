<?php
$major = (int) getenv('FIL_MAJOR');
if (! in_array($major, [3, 4, 5], true)) {
    fwrite(STDERR, "Set FIL_MAJOR to 3, 4 or 5.\n");
    exit(2);
}

require __DIR__.'/laravel_stubs.php';

$stubs = file_get_contents(__DIR__.'/stubs.php');
if ($major === 3) {
    // v3 signatures for the members that changed in v4.
    $stubs = str_replace(
        'public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema { return $schema; }',
        'public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form { return $form; }', $stubs);
    $stubs = str_replace(
        'public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null { return null; }',
        'public static function getNavigationIcon(): string|Htmlable|null { return null; }', $stubs);
    $stubs = str_replace(
        'public static function getNavigationGroup(): string|\UnitEnum|null { return null; }',
        'public static function getNavigationGroup(): ?string { return null; }', $stubs);
    $stubs = str_replace(
        'public static function getSlug(?\Filament\Panel $panel = null): string { return \'\'; }',
        'public static function getSlug(): string { return \'\'; }', $stubs);
    // v4/v5 moved Section out of Filament\Forms; on v3 it must not exist there.
    $stubs = str_replace('namespace Filament\Schemas\Components { class Section extends \Filament\Support\Components\ViewComponent {} }', '', $stubs);
}

$tmp = sys_get_temp_dir()."/frm_stubs_$major.php";
file_put_contents($tmp, $stubs);
require $tmp;

spl_autoload_register(function ($class) {
    $prefix = 'MahmoudSehsah\\FilamentResourceManager\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__.'/../../src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    if (is_file($file)) {
        require $file;
    }
});

use MahmoudSehsah\FilamentResourceManager\Support\FilamentVersion;

FilamentVersion::fake($major);

$ns = 'MahmoudSehsah\\FilamentResourceManager\\';
$variant = $major === 3 ? 'V3' : 'V4';

$classes = [
    "{$ns}Filament\\{$variant}\\ResourceSettingResource",
    "{$ns}Filament\\{$variant}\\Pages\\ListResourceSettings",
    "{$ns}Filament\\{$variant}\\Pages\\EditResourceSetting",
    "{$ns}Filament\\{$variant}\\Pages\\NavigationStudio",
    "{$ns}Navigation\\ManagedNavigationManager",
    "{$ns}FilamentResourceManagerPlugin",
    "{$ns}FilamentResourceManagerServiceProvider",
    "{$ns}Models\\ResourceSetting",
    "{$ns}Models\\NavigationProfile",
    "{$ns}Models\\NavigationProfileItem",
    "{$ns}Models\\NavigationProfileVersion",
    "{$ns}Support\\Compat",
    "{$ns}Support\\ResourceDiscovery",
    "{$ns}Support\\ResourceSynchroniser",
    "{$ns}Support\\ProfileManager",
    "{$ns}Support\\ProfileResolver",
    "{$ns}Support\\ModelCatalog",
    "{$ns}Support\\DynamicBadgeResolver",
    "{$ns}Support\\OverrideRepository",
    "{$ns}Commands\\SyncResourcesCommand",
];

$failed = 0;
foreach ($classes as $c) {
    if (! class_exists($c)) {
        fwrite(STDERR, "  FAIL  class will not load: $c\n");
        $failed++;

        continue;
    }
    echo "  ok    $c\n";
}

if ($failed) {
    exit(1);
}

$resource = "{$ns}Filament\\{$variant}\\ResourceSettingResource";

foreach ([
    [$resource, \Filament\Resources\Resource::class],
    ["{$ns}Filament\\{$variant}\\Pages\\EditResourceSetting", \Filament\Resources\Pages\EditRecord::class],
    ["{$ns}Navigation\\ManagedNavigationManager", \Filament\Navigation\NavigationManager::class],
] as [$child, $parent]) {
    if (! is_subclass_of($child, $parent)) {
        fwrite(STDERR, "  FAIL  $child does not extend $parent\n");
        exit(1);
    }
    echo "  ok    extends $parent\n";
}

if (! in_array(\Filament\Contracts\Plugin::class, class_implements("{$ns}FilamentResourceManagerPlugin"), true)) {
    fwrite(STDERR, "  FAIL  plugin contract not implemented\n");
    exit(1);
}
echo "  ok    implements Filament\\Contracts\\Plugin\n";

// The edit page must be routable, and the form fields must build against the
// component classes this major actually ships.
$pages = $resource::getPages();
foreach (['index', 'edit', 'studio'] as $page) {
    if (! array_key_exists($page, $pages)) {
        fwrite(STDERR, "  FAIL  missing '$page' page registration\n");
        exit(1);
    }
    echo "  ok    page registered: $page\n";
}

$expectedSection = $major === 3
    ? \Filament\Forms\Components\Section::class
    : 'Filament\\Schemas\\Components\\Section';

$section = "{$ns}Support\\Compat"::sectionClass();
if ($section !== $expectedSection) {
    fwrite(STDERR, "  FAIL  Compat::sectionClass() gave $section, expected $expectedSection\n");
    exit(1);
}
echo "  ok    Compat::sectionClass() -> $section\n";

$components = $resource::formComponents();
if ($components === []) {
    fwrite(STDERR, "  FAIL  formComponents() returned nothing\n");
    exit(1);
}
echo '  ok    formComponents() built '.count($components)." sections\n";

// Each panel must retain its own authorization callback. A single global
// plugin instance would make the last registered panel's rule win everywhere.
$pluginClass = "{$ns}FilamentResourceManagerPlugin";
$adminPanel = new \Filament\Panel('admin');
$appPanel = new \Filament\Panel('app');
$adminPlugin = (new $pluginClass)->authorize(fn (): bool => true);
$appPlugin = (new $pluginClass)->authorize(fn (): bool => false);
$adminPlugin->register($adminPanel);
$appPlugin->register($appPanel);

if (! $pluginClass::isAuthorized($adminPanel) || $pluginClass::isAuthorized($appPanel)) {
    fwrite(STDERR, "  FAIL  panel authorization callbacks are not isolated\n");
    exit(1);
}
echo "  ok    panel authorization callbacks are isolated\n";
