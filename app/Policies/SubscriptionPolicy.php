<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('subscriptions.view');
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->hasPermission('subscriptions.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('subscriptions.create');
    }

    public function update(User $user, Subscription $subscription): bool
    {
        // A cancelled subscription is a historical record and stays as issued.
        return $user->hasPermission('subscriptions.update')
            && ! $subscription->status->isTerminal();
    }

    /**
     * Changing service status is a separate ability from editing the record,
     * so a technician can suspend and reconnect a line without being able to
     * alter its pricing, and billing staff can do the reverse.
     */
    public function manageStatus(User $user, Subscription $subscription): bool
    {
        return $user->hasPermission('subscriptions.manage_status')
            && ! $subscription->status->isTerminal();
    }
}
