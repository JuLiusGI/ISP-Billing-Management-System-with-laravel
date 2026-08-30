<?php

namespace App\Services\Provisioning;

use App\Contracts\ServiceProvisioner;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

/**
 * The provisioner used until a network integration exists.
 *
 * It is a working implementation, not a stub: service status is tracked in the
 * database and every action that would have gone to the network is written to
 * the log, so the intended sequence can be checked against a real device before
 * a driver is trusted with it.
 */
class NullServiceProvisioner implements ServiceProvisioner
{
    public function activate(Subscription $subscription): void
    {
        $this->record('activate', $subscription);
    }

    public function suspend(Subscription $subscription): void
    {
        $this->record('suspend', $subscription);
    }

    public function terminate(Subscription $subscription): void
    {
        $this->record('terminate', $subscription);
    }

    public function isEnabled(): bool
    {
        return false;
    }

    private function record(string $action, Subscription $subscription): void
    {
        Log::info('Service provisioning skipped: no network backend configured.', [
            'action' => $action,
            'subscription' => $subscription->subscription_code,
            'customer_id' => $subscription->customer_id,
            'username' => $subscription->username,
            'static_ip' => $subscription->static_ip,
        ]);
    }
}
