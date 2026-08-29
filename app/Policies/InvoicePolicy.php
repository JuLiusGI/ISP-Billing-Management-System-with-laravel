<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('invoices.create');
    }

    /**
     * An invoice stops being editable the moment money is applied to it.
     *
     * Before that it is a document still being prepared; afterwards it is a
     * historical record that a payment and a receipt already refer to, so
     * rewriting its figures would falsify both.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.update') && $this->isAmendable($invoice);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.cancel') && $this->isAmendable($invoice);
    }

    /** Printing is available to anyone who may read the invoice. */
    public function print(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.view');
    }

    private function isAmendable(Invoice $invoice): bool
    {
        if (in_array($invoice->status, [InvoiceStatus::Cancelled, InvoiceStatus::Void], true)) {
            return false;
        }

        return $invoice->allocations()->doesntExist();
    }
}
