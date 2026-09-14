<?php

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

    'model' => MahmoudSehsah\FilamentResourceManager\Models\ResourceSetting::class,

    /*
    |--------------------------------------------------------------------------
    | Navigation profiles
    |--------------------------------------------------------------------------
    |
    | Profiles keep a mutable draft and an immutable published version. The
    | optional role resolver receives the authenticated user and should return
    | role names or IDs when the application does not use Spatie Permission.
    |
    */

    'profiles' => [
        'enabled' => true,
        'models' => [
            'profile' => MahmoudSehsah\FilamentResourceManager\Models\NavigationProfile::class,
            'item' => MahmoudSehsah\FilamentResourceManager\Models\NavigationProfileItem::class,
            'version' => MahmoudSehsah\FilamentResourceManager\Models\NavigationProfileVersion::class,
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
    */

    'icons' => [
        'cache' => true,
        'cache_key' => 'filament-resource-manager.icons',
        'cache_ttl' => 86400,
        'sets' => [],
        'limit' => 50,
        'max' => 5000,
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
