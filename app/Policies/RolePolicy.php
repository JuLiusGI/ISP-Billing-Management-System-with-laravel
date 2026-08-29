<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        if (! $user->hasPermission('roles.manage')) {
            return false;
        }

        // Super admin bypasses every check in Gate::before, so editing its
        // permission list would imply a restriction that does not exist.
        return $role->name !== Role::SUPER_ADMIN;
    }

    public function delete(User $user, Role $role): bool
    {
        if (! $user->hasPermission('roles.manage')) {
            return false;
        }

        // Roles the application depends on stay put, and a role still in use
        // must be emptied before it can go.
        return ! $role->is_system && $role->users()->count() === 0;
    }
}
