<?php

namespace MahmoudSehsah\FilamentResourceManager\Models;

use Illuminate\Database\Eloquent\Model;

class NavigationProfileVersion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'published_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return $this->table
            ?? config('filament-resource-manager.profiles.tables.versions', 'filament_navigation_profile_versions');
    }

    public function profile()
    {
        return $this->belongsTo(config(
            'filament-resource-manager.profiles.models.profile',
            NavigationProfile::class,
        ), 'profile_id');
    }
}
