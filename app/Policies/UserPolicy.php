<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;

/**
 * Authorisation for staff account management.
 *
 * Policy abilities are not short-circuited by the Gate::before hook, so every
 * method here runs for super admins too. Access is still granted to them,
 * because hasPermission() returns true for a super admin, but the lockout
 * guards in delete() and saveWith() apply to everyone without exception.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('users.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('users.update');
    }

    public function delete(User $user, User $target): bool
    {
        if (! $user->hasPermission('users.delete')) {
            return false;
        }

        // You cannot remove your own way back in.
        if ($user->is($target)) {
            return false;
        }

        return ! $this->isLastSuperAdmin($target);
    }

    /**
     * Whether $user may save $target with this status and role set.
     *
     * Checked separately from update() because it depends on the submitted
     * values, not just on who is being edited.
     *
     * @param  array<int, int|string>  $roleIds
     */
    public function saveWith(User $user, User $target, string $status, array $roleIds): bool
    {
        if (! $user->is($target)) {
            return true;
        }

        // Suspending or deactivating yourself would end your own session.
        if ($status !== UserStatus::Active->value) {
            return false;
        }

        if (! $target->isSuperAdmin() || ! $this->isLastSuperAdmin($target)) {
            return true;
        }

        // The last super admin must keep the role.
        $superAdminId = (string) Role::where('name', Role::SUPER_ADMIN)->value('id');

        return in_array($superAdminId, array_map('strval', $roleIds), true);
    }

    private function isLastSuperAdmin(User $target): bool
    {
        return $target->isSuperAdmin()
            && User::withRole(Role::SUPER_ADMIN)->count() <= 1;
    }
}
