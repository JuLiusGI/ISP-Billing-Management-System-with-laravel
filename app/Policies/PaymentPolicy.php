<?php

namespace App\Policies;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('payments.create');
    }

    /**
     * Applying leftover credit is part of recording money, not of reversing
     * it, so it rides on payments.create.
     */
    public function allocate(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.create')
            && $payment->status === PaymentStatus::Completed
            && ! $payment->isFullyAllocated();
    }

    /**
     * Reversal is a separate ability. A cashier records money; undoing it is
     * an accounting correction.
     */
    public function reverse(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.reverse')
            && $payment->status === PaymentStatus::Completed;
    }
}
