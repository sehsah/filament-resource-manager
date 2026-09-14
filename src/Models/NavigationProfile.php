<?php

namespace MahmoudSehsah\FilamentResourceManager\Models;

use Illuminate\Database\Eloquent\Model;
use MahmoudSehsah\FilamentResourceManager\Support\OverrideRepository;

class NavigationProfile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_default' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(static fn (): mixed => OverrideRepository::flush());
        static::deleted(static fn (): mixed => OverrideRepository::flush());

        static::deleting(static function (self $profile): void {
            $profile->items()->delete();
            $profile->versions()->delete();
        });
    }

    public function getTable(): string
    {
        return $this->table
            ?? config('filament-resource-manager.profiles.tables.profiles', 'filament_navigation_profiles');
    }

    public function items()
    {
        return $this->hasMany(config(
            'filament-resource-manager.profiles.models.item',
            NavigationProfileItem::class,
        ), 'profile_id');
    }

    public function versions()
    {
        return $this->hasMany(config(
            'filament-resource-manager.profiles.models.version',
            NavigationProfileVersion::class,
        ), 'profile_id');
    }

    public function publishedVersion()
    {
        return $this->belongsTo(config(
            'filament-resource-manager.profiles.models.version',
            NavigationProfileVersion::class,
        ), 'published_version_id');
    }
}
