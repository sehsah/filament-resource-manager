<?php

namespace MahmoudSehsah\FilamentResourceManager\Models;

use Illuminate\Database\Eloquent\Model;
use MahmoudSehsah\FilamentResourceManager\Support\OverrideRepository;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileManager;

/**
 * @property int $id
 * @property string|null $panel_id
 * @property string $resource_class
 * @property string|null $label
 * @property string|null $icon
 * @property string|null $active_icon
 * @property string|null $navigation_group
 * @property bool $navigation_group_overridden
 * @property string|null $navigation_parent_item
 * @property string|null $parent_resource_class
 * @property int|null $sort
 * @property bool $is_visible
 * @property string|null $badge
 * @property string $badge_type
 * @property string|null $badge_model
 * @property array<int, array<string, mixed>>|null $badge_conditions
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
        'navigation_group_overridden' => 'boolean',
        'is_orphaned' => 'boolean',
        'badge_conditions' => 'array',
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
        static::saved(static function (ResourceSetting $setting): void {
            OverrideRepository::flush();
            ProfileManager::syncSetting($setting);
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

    /**
     * Three states, same as a profile item: an explicit group, an explicit
     * "no group at all", or fall through to whatever the resource declares.
     */
    public function getEffectiveNavigationGroupAttribute(): ?string
    {
        if ($this->navigation_group_overridden) {
            return $this->navigation_group;
        }

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
        if ($this->navigation_group_overridden) {
            return false;
        }

        foreach (OverrideRepository::ATTRIBUTES as $attribute) {
            if (in_array($attribute, ['is_visible', 'navigation_group_overridden'], true)) {
                continue;
            }

            if (($this->badge_type ?? 'static') !== 'dynamic'
                && in_array($attribute, ['badge_type', 'badge_model', 'badge_conditions'], true)) {
                continue;
            }

            if (filled($this->{$attribute})) {
                return false;
            }
        }

        return (bool) $this->is_visible;
    }
}
