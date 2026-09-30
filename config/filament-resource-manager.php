<?php

use MahmoudSehsah\FilamentResourceManager\Models\NavigationProfile;
use MahmoudSehsah\FilamentResourceManager\Models\NavigationProfileItem;
use MahmoudSehsah\FilamentResourceManager\Models\NavigationProfileVersion;
use MahmoudSehsah\FilamentResourceManager\Models\ResourceSetting;

return [

    /*
    |--------------------------------------------------------------------------
    | Database table
    |--------------------------------------------------------------------------
    */

    'table_name' => 'filament_resource_settings',

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    |
    | Swap this for your own model if you need extra behaviour. It must extend
    | the package model.
    |
    */

    'model' => ResourceSetting::class,

    /*
    |--------------------------------------------------------------------------
    | Navigation profiles
    |--------------------------------------------------------------------------
    |
    | Profiles keep a mutable draft and restorable published versions. Old
    | versions may be deleted, while the current published version is always
    | protected.
    |
    | "auto_publish" republishes the governing profile whenever a resource is
    | changed from the Resource Manager (edit page, table toggles, reordering,
    | reset), so the change is live at once. Each such change adds a version.
    | Set it to false to collect changes in the draft and publish them yourself
    | from Navigation Studio. Layout changes made in the studio itself always
    | wait for an explicit publish - but note that an automatic publish takes
    | the whole draft live, including any unpublished studio layout.
    |
    */

    'profiles' => [
        'enabled' => true,
        'auto_publish' => true,
        'models' => [
            'profile' => NavigationProfile::class,
            'item' => NavigationProfileItem::class,
            'version' => NavigationProfileVersion::class,
        ],
        'tables' => [
            'profiles' => 'filament_navigation_profiles',
            'items' => 'filament_navigation_profile_items',
            'versions' => 'filament_navigation_profile_versions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic sync
    |--------------------------------------------------------------------------
    |
    | When enabled, opening the manager screen creates a settings row for every
    | resource registered on the panel that does not have one yet, and marks
    | rows whose resource class no longer exists as orphaned.
    |
    */

    'auto_sync' => true,

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Overrides are read on every request that renders navigation, so they are
    | cached. Set "store" to null to use the default cache store, or set
    | "enabled" to false while debugging.
    |
    */

    'cache' => [
        'enabled' => true,
        'store' => null,
        'ttl' => 3600,
        'key' => 'filament-resource-manager.overrides',
    ],

    /*
    |--------------------------------------------------------------------------
    | Icon picker
    |--------------------------------------------------------------------------
    |
    | The icon fields offer whatever icon sets Blade Icons has registered. That
    | list is built by scanning those sets from disk, so it is cached.
    |
    | "sets" limits the picker to particular set prefixes, e.g. ['heroicon'];
    | leave it empty to offer every registered set. "limit" is how many icons a
    | search returns, and "max" caps the catalogue itself.
    |
    | "types" are the ways an icon can be given, in the order they are offered:
    |   icon  - picked from the installed icon sets
    |   code  - an icon name typed by hand, e.g. heroicon-o-users
    |   svg   - raw SVG markup, sanitised before it is rendered
    |   image - an uploaded image, stored on "upload.disk"
    | Remove a type to hide it from the form.
    |
    */

    'icons' => [
        'cache' => true,
        'cache_key' => 'filament-resource-manager.icons',
        'cache_ttl' => 86400,
        'sets' => [],
        'limit' => 50,
        'max' => 5000,
        'types' => ['icon', 'code', 'svg', 'image'],
        'svg_max_length' => 50000,
        'upload' => [
            'disk' => 'public',
            'directory' => 'navigation-icons',
            'max_size' => 1024, // kilobytes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic navigation badges
    |--------------------------------------------------------------------------
    |
    | Models used by resources on the current panel are available automatically.
    | Add models without a Filament resource here. Use either a simple list or
    | map a model class to the label shown in the picker.
    |
    | 'models' => [
    |     App\Models\Ticket::class,
    |     App\Models\Invoice::class => 'Invoices',
    | ],
    |
    */

    'dynamic_badges' => [
        'models' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources excluded from management
    |--------------------------------------------------------------------------
    |
    | Fully-qualified resource class names listed here never appear in the
    | manager and are never overridden.
    |
    */

    'excluded_resources' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Access control (Roles & Permissions)
    |--------------------------------------------------------------------------
    |
    | When Spatie Laravel Permission is installed, roles and permissions are
    | discovered automatically. You can also define custom roles and permissions
    | here, or configure the models used for discovery.
    |
    */

    'access_control' => [
        'roles' => [],
        'permissions' => [],
        'models' => [
            'role' => 'Spatie\\Permission\\Models\\Role',
            'permission' => 'Spatie\\Permission\\Models\\Permission',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | "gate" is an ability checked against the authenticated user. Leave it null
    | to allow every user who can access the panel, or register a closure with
    | FilamentResourceManagerPlugin::make()->authorize(fn () => ...).
    |
    */

    'gate' => null,

    /*
    |--------------------------------------------------------------------------
    | Manager navigation
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        'icon' => 'heroicon-o-squares-2x2',
        'group' => null,
        'sort' => null,
        'slug' => 'resource-manager',
        'register' => true,
    ],

];
