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
     * Whether a receipt may be issued for this payment.
     *
     * It lives here rather than on ReceiptPolicy because Laravel resolves a
     * policy from the argument's class, and the argument is a Payment: the
     * receipt does not exist yet. A reversed payment is money the ISP no
     * longer holds, so there is nothing to acknowledge.
     */
    public function issueReceipt(User $user, Payment $payment): bool
    {
        return $user->hasPermission('receipts.issue')
            && $payment->status === PaymentStatus::Completed
            && $payment->receipt()->doesntExist();
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
