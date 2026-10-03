<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use Throwable;

/** A rule simulation, without impersonation or executing application policies. */
class RolePreview
{
    public static function permissions(array $roles): array
    {
        $permissions = [];
        $mapping = (array) config('filament-resource-manager.access_control.role_permissions', []);
        foreach ($roles as $role) {
            $permissions = [...$permissions, ...(array) ($mapping[$role] ?? [])];
        }
        $model = config('filament-resource-manager.access_control.models.role');
        if (is_string($model) && class_exists($model)) {
            try {
                $query = $model::query()->whereIn('name', $roles);
                if (TableColumns::has((new $model)->getTable(), ['guard_name'])) {
                    $query->where('guard_name', ResourceDiscovery::panel()?->getAuthGuard() ?? config('auth.defaults.guard'));
                }
                foreach ($query->with('permissions')->get() as $role) {
                    $permissions = [...$permissions, ...$role->permissions->pluck('name')->all()];
                }
            } catch (Throwable) {
                // Custom role systems can supply the explicit mapping instead.
            }
        }

        return array_values(array_unique($permissions));
    }

    /** @return array<string, array<int, string>> Reasons keyed by resource class. */
    public static function reasons(array $items, array $roles, array $permissions, array $audience = []): array
    {
        $result = [];
        foreach ($items as $item) {
            $reasons = [];
            if ($audience !== [] && array_intersect($audience, $roles) === []) {
                $reasons[] = 'audience';
            }
            if (! ($item['is_visible'] ?? true)) {
                $reasons[] = 'hidden';
            }
            foreach (['roles' => $roles, 'permissions' => $permissions] as $field => $selected) {
                $required = array_values(array_filter((array) ($item[$field] ?? [])));
                if ($required !== [] && ! AccessResolver::evaluateCondition($selected, $required, $item[$field.'_condition'] ?? 'any')) {
                    $reasons[] = $field;
                }
            }
            $result[$item['resource_class']] = $reasons;
        }

        return $result;
    }
}
