<?php

namespace MahmoudSehsah\FilamentResourceManager\Models;

use Illuminate\Database\Eloquent\Model;
use MahmoudSehsah\FilamentResourceManager\Support\OverrideRepository;

class NavigationProfileItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sort' => 'integer',
        'navigation_group_overridden' => 'boolean',
        'is_visible' => 'boolean',
        'is_orphaned' => 'boolean',
        'badge_conditions' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(static function (): void {
            OverrideRepository::flush();
        });
        static::deleted(static function (): void {
            OverrideRepository::flush();
        });
    }

    public function getTable(): string
    {
        return $this->table
            ?? config('filament-resource-manager.profiles.tables.items', 'filament_navigation_profile_items');
    }

    public function profile()
    {
        return $this->belongsTo(config(
            'filament-resource-manager.profiles.models.profile',
            NavigationProfile::class,
        ), 'profile_id');
    }

    public function getEffectiveLabelAttribute(): ?string
    {
        return filled($this->label) ? $this->label : $this->default_label;
    }

    public function getEffectiveIconAttribute(): ?string
    {
        return filled($this->icon) ? $this->icon : $this->default_icon;
    }

    public function getEffectiveNavigationGroupAttribute(): ?string
    {
        return $this->navigation_group_overridden
            ? $this->navigation_group
            : $this->default_navigation_group;
    }
}
