<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('expenses.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->hasPermission('expenses.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('expenses.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->hasPermission('expenses.update') && ! $expense->trashed();
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->hasPermission('expenses.delete') && ! $expense->trashed();
    }

    public function restore(User $user, Expense $expense): bool
    {
        return $user->hasPermission('expenses.delete') && $expense->trashed();
    }
}
