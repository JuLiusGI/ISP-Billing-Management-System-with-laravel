<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

/**
 * Categories are part of the expense module rather than system settings, so
 * whoever maintains the books maintains the chart they are filed under.
 * Viewing rides on expenses.view; changing the list needs expenses.update.
 */
class ExpenseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('expenses.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('expenses.update');
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $user->hasPermission('expenses.update');
    }

    /**
     * A category that has been used is never deleted; it is deactivated, so
     * historical expenses keep a meaningful label.
     */
    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $user->hasPermission('expenses.update') && $category->expenses()->doesntExist();
    }
}
