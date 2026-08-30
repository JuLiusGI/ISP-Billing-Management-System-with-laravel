<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * The trail is read-only by design. There is no create, update or delete
 * ability here, and none is offered through the application: an audit log that
 * can be edited from the interface it audits is not evidence of anything.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('audit_logs.view');
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $user->hasPermission('audit_logs.view');
    }
}
