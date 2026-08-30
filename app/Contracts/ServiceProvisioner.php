<?php

namespace App\Contracts;

use App\Models\Subscription;

/**
 * The seam where this application would talk to the network.
 *
 * Enabling or cutting a customer's line is a side effect of a status change,
 * not part of it, so the call sites live here rather than inside the billing
 * domain. A MikroTik or RADIUS driver implements this interface later and is
 * bound in place of the null driver; nothing else has to move.
 *
 * Implementations must be safe to call repeatedly with the same subscription,
 * because a status change may be retried.
 */
interface ServiceProvisioner
{
    /** Bring the customer's line up. */
    public function activate(Subscription $subscription): void;

    /** Take the line down while keeping the account provisioned. */
    public function suspend(Subscription $subscription): void;

    /** Remove the account from the network entirely. */
    public function terminate(Subscription $subscription): void;

    /**
     * Whether a real network backend is wired up. False for the null driver,
     * which lets the UI say that changes are recorded but not pushed.
     */
    public function isEnabled(): bool;
}
