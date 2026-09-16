<?php

namespace MahmoudSehsah\FilamentResourceManager\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
    public $timestamps = false;

    protected $guarded = [];

    /** @var array<int, string> */
    public array $assignedRoles = [];

    /** @var array<int, string> */
    public array $assignedPermissions = [];

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->assignedRoles, true);
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return array_intersect($roles, $this->assignedRoles) !== [];
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAllRoles(array $roles): bool
    {
        return count(array_intersect($roles, $this->assignedRoles)) === count($roles);
    }

    public function getRoleNames()
    {
        return collect($this->assignedRoles);
    }

    public function can($abilities, $arguments = []): bool
    {
        $abilities = (array) $abilities;

        foreach ($abilities as $ability) {
            if (in_array($ability, $this->assignedPermissions, true)) {
                return true;
            }
        }

        return false;
    }
}
