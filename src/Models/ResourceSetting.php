<?php

namespace MahmoudSehsah\FilamentResourceManager\Models;

use Illuminate\Database\Eloquent\Model;
use MahmoudSehsah\FilamentResourceManager\Support\OverrideRepository;

/**
 * @property int $id
 * @property string|null $panel_id
 * @property string $resource_class
 * @property string|null $label
 * @property string|null $icon
 * @property string|null $active_icon
 * @property string|null $navigation_group
 * @property string|null $navigation_parent_item
 * @property string|null $parent_resource_class
 * @property int|null $sort
 * @property bool $is_visible
 * @property string|null $badge
 * @property string|null $badge_color
 * @property string|null $badge_tooltip
 * @property string|null $default_label
 * @property string|null $default_icon
 * @property string|null $default_navigation_group
 * @property bool $is_orphaned
 */
class ResourceSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sort' => 'integer',
        'is_visible' => 'boolean',
        'is_orphaned' => 'boolean',
    ];

    /**
     * Navigation overrides are cached, so every write has to clear that cache or
     * an administrator's edit would not show up until the entry expired.
     *
     * Binding this to the model rather than to the edit page covers every way a
     * row is written: saving the edit form, toggling visibility inline in the
     * table, dragging a row to reorder it, and anything an application does to
     * the model directly.
     *
     * Mass updates - the "Reset all" action, and the synchroniser's orphan pass -
     * bypass model events by design, and flush explicitly instead.
     */
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
            ?? config('filament-resource-manager.table_name', 'filament_resource_settings');
    }

    /**
     * The label shown to an administrator: their override if set, otherwise the
     * label the resource itself declares.
     */
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
        return filled($this->navigation_group)
            ? $this->navigation_group
            : $this->default_navigation_group;
    }

    /**
     * True when this row overrides nothing, so the resource renders as it would
     * without the package installed.
     */
    public function isPassthrough(): bool
    {
        foreach (OverrideRepository::ATTRIBUTES as $attribute) {
            if ($attribute === 'is_visible') {
                continue;
            }

            if (filled($this->{$attribute})) {
                return false;
            }
        }

        return (bool) $this->is_visible;
    }
}
