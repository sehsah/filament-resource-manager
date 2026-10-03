<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use MahmoudSehsah\FilamentResourceManager\FilamentResourceManagerPlugin;
use Throwable;

class AccessResolver
{
    /**
     * @param  array<string, mixed>  $override
     */
    public static function canAccessItem(array $override, ?Authenticatable $user = null): bool
    {
        if (($override['is_visible'] ?? true) === false) {
            return false;
        }

        $roles = array_values(array_filter((array) ($override['roles'] ?? [])));
        $permissions = array_values(array_filter((array) ($override['permissions'] ?? [])));

        if ($roles === [] && $permissions === []) {
            return true;
        }

        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        if ($roles !== []) {
            $condition = ($override['roles_condition'] ?? 'any') === 'all' ? 'all' : 'any';

            if (! static::userHasRoles($user, $roles, $condition)) {
                return false;
            }
        }

        if ($permissions !== []) {
            $condition = ($override['permissions_condition'] ?? 'any') === 'all' ? 'all' : 'any';

            if (! static::userHasPermissions($user, $permissions, $condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $roles
     */
    public static function userHasRoles(Authenticatable $user, array $roles, string $condition = 'any'): bool
    {
        $roles = array_values(array_filter($roles));

        if ($roles === []) {
            return true;
        }

        $plugin = FilamentResourceManagerPlugin::get();

        if ($plugin?->getUserRolesResolver() !== null) {
            $userRoles = (array) call_user_func($plugin->getUserRolesResolver(), $user);

            return static::evaluateCondition($userRoles, $roles, $condition);
        }

        // Spatie Laravel Permission helper methods
        if ($condition === 'any' && method_exists($user, 'hasAnyRole')) {
            try {
                return (bool) $user->hasAnyRole($roles);
            } catch (Throwable) {
                // Fall through to manual check
            }
        }

        if ($condition === 'all' && method_exists($user, 'hasAllRoles')) {
            try {
                return (bool) $user->hasAllRoles($roles);
            } catch (Throwable) {
                // Fall through to manual check
            }
        }

        $userRoles = static::getUserRoles($user);

        return static::evaluateCondition($userRoles, $roles, $condition);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public static function userHasPermissions(Authenticatable $user, array $permissions, string $condition = 'any'): bool
    {
        $permissions = array_values(array_filter($permissions));

        if ($permissions === []) {
            return true;
        }

        $plugin = FilamentResourceManagerPlugin::get();

        if ($plugin?->getUserPermissionsResolver() !== null) {
            $userPermissions = (array) call_user_func($plugin->getUserPermissionsResolver(), $user);

            return static::evaluateCondition($userPermissions, $permissions, $condition);
        }

        $condition = $condition === 'all' ? 'all' : 'any';

        foreach ($permissions as $permission) {
            $hasPermission = false;

            try {
                if (method_exists($user, 'can')) {
                    $hasPermission = (bool) $user->can($permission);
                } elseif (method_exists($user, 'hasPermissionTo')) {
                    $hasPermission = (bool) $user->hasPermissionTo($permission);
                }
            } catch (Throwable) {
                $hasPermission = false;
            }

            if ($condition === 'any' && $hasPermission) {
                return true;
            }

            if ($condition === 'all' && ! $hasPermission) {
                return false;
            }
        }

        return $condition === 'all';
    }

    /**
     * @return array<int, string>
     */
    public static function getUserRoles(Authenticatable $user): array
    {
        $plugin = FilamentResourceManagerPlugin::get();

        if ($plugin?->getUserRolesResolver() !== null) {
            return array_values(array_map('strval', (array) call_user_func($plugin->getUserRolesResolver(), $user)));
        }

        try {
            if (method_exists($user, 'getRoleNames')) {
                return array_values(array_map('strval', $user->getRoleNames()->all()));
            }

            if (isset($user->roles) && is_iterable($user->roles)) {
                $roles = [];
                foreach ($user->roles as $role) {
                    $roles[] = is_object($role) ? (string) ($role->name ?? $role->key ?? '') : (string) $role;
                }

                return array_values(array_filter($roles));
            }

            if (isset($user->role) && is_scalar($user->role)) {
                return [(string) $user->role];
            }
        } catch (Throwable) {
            return [];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function getAvailableRoles(): array
    {
        $roles = (array) config('filament-resource-manager.access_control.roles', []);
        $options = [];

        foreach ($roles as $key => $label) {
            if (is_numeric($key)) {
                $options[(string) $label] = (string) $label;
            } else {
                $options[(string) $key] = (string) $label;
            }
        }

        // Spatie Permission auto-discovery
        $spatieRoleModel = config('filament-resource-manager.access_control.models.role', 'Spatie\\Permission\\Models\\Role');

        if (class_exists($spatieRoleModel)) {
            try {
                /** @var array<string, string> $spatieRoles */
                $spatieRoles = $spatieRoleModel::query()->pluck('name', 'name')->all();
                $options = array_merge($options, $spatieRoles);
            } catch (Throwable) {
                // Table might not exist yet
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function getAvailablePermissions(): array
    {
        $permissions = (array) config('filament-resource-manager.access_control.permissions', []);
        $options = [];

        foreach ($permissions as $key => $label) {
            if (is_numeric($key)) {
                $options[(string) $label] = (string) $label;
            } else {
                $options[(string) $key] = (string) $label;
            }
        }

        // Spatie Permission auto-discovery
        $spatiePermModel = config('filament-resource-manager.access_control.models.permission', 'Spatie\\Permission\\Models\\Permission');

        if (class_exists($spatiePermModel)) {
            try {
                /** @var array<string, string> $spatiePermissions */
                $spatiePermissions = $spatiePermModel::query()->pluck('name', 'name')->all();
                $options = array_merge($options, $spatiePermissions);
            } catch (Throwable) {
                // Table might not exist yet
            }
        }

        return $options;
    }

    /**
     * @param  array<int, string>  $userItems
     * @param  array<int, string>  $targetItems
     */
    public static function evaluateCondition(array $userItems, array $targetItems, string $condition): bool
    {
        $intersection = array_intersect($targetItems, $userItems);

        return $condition === 'all'
            ? count($intersection) === count($targetItems)
            : count($intersection) > 0;
    }
}
