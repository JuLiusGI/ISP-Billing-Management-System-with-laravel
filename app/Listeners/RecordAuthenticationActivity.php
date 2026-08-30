<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Records who signed in, who signed out, and who tried and failed.
 *
 * Failed attempts matter more than successful ones for an audit trail: a run
 * of them against one address is the shape of an attack, and it is invisible
 * if only successes are recorded.
 */
class RecordAuthenticationActivity
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        $this->audit->log(
            action: 'login',
            module: 'Authentication',
            subject: $user instanceof User ? $user : null,
            description: 'Signed in',
            actor: $user instanceof User ? $user : null,
        );
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->audit->log(
            action: 'logout',
            module: 'Authentication',
            subject: $user,
            description: 'Signed out',
            actor: $user,
        );
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        $this->audit->log(
            action: 'login_failed',
            module: 'Authentication',
            subject: $event->user instanceof User ? $event->user : null,
            // The address tried is recorded; the password never is.
            description: 'Failed sign-in attempt for '.($email ?: 'an unknown address'),
        );
    }

    public function handleLockout(Lockout $event): void
    {
        $email = $event->request->input('email');

        $this->audit->log(
            action: 'login_throttled',
            module: 'Authentication',
            description: 'Too many failed attempts for '.($email ?: 'an unknown address'),
        );
    }
}
