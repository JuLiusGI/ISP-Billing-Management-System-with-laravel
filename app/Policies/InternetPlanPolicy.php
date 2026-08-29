<?php

namespace App\Policies;

use App\Models\InternetPlan;
use App\Models\User;

class InternetPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('plans.view');
    }

    public function view(User $user, InternetPlan $plan): bool
    {
        return $user->hasPermission('plans.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('plans.create');
    }

    public function update(User $user, InternetPlan $plan): bool
    {
        return $user->hasPermission('plans.update');
    }

    /**
     * A plan that has ever been subscribed to is never removed, even softly.
     * Subscriptions and invoices refer to it by name for their history, so
     * retiring one means deactivating it.
     */
    public function delete(User $user, InternetPlan $plan): bool
    {
        return $user->hasPermission('plans.delete')
            && $plan->subscriptions()->withTrashed()->doesntExist();
    }
}
