<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ProfileTransfer
{
    public const MAX_BYTES = 1048576;

    public static function attributes(): array
    {
        return array_values(array_diff(ProfileManager::ITEM_ATTRIBUTES, [
            'default_label', 'default_icon', 'default_navigation_group', 'is_orphaned',
        ]));
    }

    public static function export(Model $profile): string
    {
        return json_encode([
            'format' => 'filament-resource-manager',
            'version' => 1,
            'name' => $profile->name,
            'roles' => (array) $profile->roles,
            'items' => array_map(fn (array $item): array => array_intersect_key($item, array_flip(static::attributes())), ProfileManager::snapshot($profile)),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** Validate again at import time; never trust a client-provided preview. */
    public static function prepare(string $json, Panel $panel): array
    {
        if (strlen($json) > static::MAX_BYTES) {
            throw new InvalidArgumentException(__('filament-resource-manager::manager.transfer.too_large'));
        }

        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException(__('filament-resource-manager::manager.transfer.invalid_json'));
        }

        $rules = [
            'format' => ['required', Rule::in(['filament-resource-manager'])],
            'version' => ['required', Rule::in([1])],
            'name' => ['required', 'string', 'max:255'],
            'roles' => ['present', 'array', 'max:100'],
            'roles.*' => ['string', 'distinct', Rule::in(array_keys(AccessResolver::getAvailableRoles()))],
            'items' => ['present', 'array', 'max:1000'],
            'items.*' => ['array:'.implode(',', static::attributes())],
        ];
        foreach (static::attributes() as $attribute) {
            $rules['items.*.'.$attribute] = ['nullable', 'string', 'max:255'];
        }
        $rules['items.*.resource_class'] = ['required', 'string', 'distinct', Rule::in(ResourceDiscovery::resourcesFor($panel))];
        foreach (['is_visible', 'navigation_group_overridden'] as $attribute) {
            $rules['items.*.'.$attribute] = ['required', 'boolean'];
        }
        $rules['items.*.sort'] = ['nullable', 'integer', 'min:-2147483648', 'max:2147483647'];
        foreach (['roles', 'permissions'] as $attribute) {
            $options = $attribute === 'roles' ? AccessResolver::getAvailableRoles() : AccessResolver::getAvailablePermissions();
            $rules['items.*.'.$attribute] = ['nullable', 'array', 'max:100'];
            $rules['items.*.'.$attribute.'.*'] = ['string', Rule::in(array_keys($options))];
            $rules['items.*.'.$attribute.'_condition'] = ['nullable', Rule::in(['any', 'all'])];
        }
        foreach (['icon', 'active_icon'] as $slot) {
            $rules['items.*.'.$slot.'_type'] = ['nullable', Rule::in(['icon', 'code', 'svg', 'image'])];
            $rules['items.*.'.$slot.'_svg'] = ['nullable', 'string', 'max:'.(int) config('filament-resource-manager.icons.svg_max_length', 50000)];
        }
        $rules['items.*.badge_type'] = ['nullable', Rule::in(['static', 'dynamic'])];
        $rules['items.*.badge_conditions'] = ['nullable', 'array', 'max:20'];
        $rules['items.*.badge_conditions.*'] = ['array:column,operator,value'];
        $rules['items.*.badge_conditions.*.column'] = ['required', 'string', 'max:255'];
        $rules['items.*.badge_conditions.*.operator'] = ['required', Rule::in(array_keys(__('filament-resource-manager::manager.badge_operators')))];
        $rules['items.*.badge_conditions.*.value'] = ['nullable', function ($attribute, $value, $fail): void {
            if (! is_scalar($value) || (is_string($value) && mb_strlen($value) > 255)) {
                $fail(__('filament-resource-manager::manager.transfer.invalid_value'));
            }
        }];
        $data = Validator::make(is_array($data) ? $data : [], $rules)->validate();
        if (! array_is_list($data['items']) || ! array_is_list($data['roles'])) {
            throw new InvalidArgumentException(__('filament-resource-manager::manager.transfer.invalid_json'));
        }
        $parents = array_column($data['items'], 'parent_resource_class', 'resource_class');
        $resources = array_column($data['items'], 'resource_class');
        foreach ($data['items'] as &$item) {
            foreach (['roles_condition' => 'any', 'permissions_condition' => 'any', 'badge_type' => 'static'] as $field => $default) {
                $item[$field] ??= $default;
            }
            $seen = [$item['resource_class']];
            $parent = $item['parent_resource_class'] ?? null;
            while ($parent !== null) {
                if (! in_array($parent, $resources, true) || in_array($parent, $seen, true)) {
                    throw new InvalidArgumentException(__('filament-resource-manager::manager.transfer.invalid_parent'));
                }
                $seen[] = $parent;
                $parent = $parents[$parent] ?? null;
            }
            if (($item['badge_type'] ?? 'static') === 'dynamic') {
                if (! ModelCatalog::contains($item['badge_model'] ?? '')) {
                    throw new InvalidArgumentException(__('filament-resource-manager::manager.transfer.invalid_badge'));
                }
                $columns = array_keys(ModelCatalog::columns($item['badge_model']));
                foreach ($item['badge_conditions'] ?? [] as $condition) {
                    if (! in_array($condition['column'], $columns, true)) {
                        throw new InvalidArgumentException(__('filament-resource-manager::manager.transfer.invalid_badge'));
                    }
                }
            }
            foreach (['icon', 'active_icon'] as $slot) {
                if (($item[$slot.'_type'] ?? 'icon') === 'svg' && filled($item[$slot.'_svg'] ?? null)) {
                    $svg = NavigationIcon::sanitizeSvg($item[$slot.'_svg']);
                    if ($svg === null) {
                        throw new InvalidArgumentException(__('filament-resource-manager::manager.fields.icon_svg_invalid'));
                    }
                    $item[$slot.'_svg'] = $svg;
                }
                $item = array_merge($item, NavigationIcon::normalize($item, $slot));
            }
        }
        unset($item);

        return $data;
    }

    public static function import(string $json, Panel $panel, string $name): Model
    {
        $data = static::prepare($json, $panel);
        $name = trim($name);
        Validator::make(['name' => $name], ['name' => ['required', 'string', 'max:150']])->validate();

        return DB::transaction(function () use ($data, $panel, $name): Model {
            $profile = ProfileManager::create($panel->getId(), $name);
            $profile->forceFill(['roles' => $data['roles']])->save();
            $resources = ResourceDiscovery::resourcesFor($panel);
            // Reconcile only the new draft; a global sync could change existing
            // drafts and queue an automatic publication of their settings.
            $profile->items()->whereNotIn('resource_class', $resources)->delete();
            foreach ($resources as $resource) {
                $profile->items()->firstOrCreate(['resource_class' => $resource], [
                    'default_label' => ResourceDiscovery::defaultLabel($resource),
                    'default_icon' => ResourceDiscovery::defaultIcon($resource),
                    'default_navigation_group' => ResourceDiscovery::defaultGroup($resource),
                    'sort' => ResourceDiscovery::defaultSort($resource),
                    'is_visible' => true,
                    'is_orphaned' => false,
                ]);
            }
            foreach ($data['items'] as $item) {
                $resource = $item['resource_class'];
                $profile->items()->updateOrCreate(['resource_class' => $resource], array_merge(array_fill_keys(static::attributes(), null), $item, [
                    'default_label' => ResourceDiscovery::defaultLabel($resource),
                    'default_icon' => ResourceDiscovery::defaultIcon($resource),
                    'default_navigation_group' => ResourceDiscovery::defaultGroup($resource),
                    'is_orphaned' => false,
                ]));
            }

            return $profile->fresh();
        });
    }
}
