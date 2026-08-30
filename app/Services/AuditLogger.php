<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes the audit trail.
 *
 * Everything funnels through here so the actor, IP address and user agent are
 * captured the same way every time, rather than each caller remembering to.
 *
 * Two deliberate properties:
 *
 *   - Writes run inside whatever transaction the caller is already in, so an
 *     audit entry can never survive a change that rolled back.
 *   - A failure to write the trail is logged, never thrown. Losing an audit
 *     row is bad; refusing a customer's payment because the audit table is
 *     unavailable is worse, and the application log still records that it
 *     happened.
 */
class AuditLogger
{
    /** Never written to the trail, whatever a model declares. */
    private const ALWAYS_REDACTED = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function log(
        string $action,
        string $module,
        ?Model $subject = null,
        ?string $description = null,
        ?array $old = null,
        ?array $new = null,
        ?User $actor = null,
    ): ?AuditLog {
        try {
            return AuditLog::create([
                'user_id' => ($actor ?? Auth::user())?->id,
                'action' => $action,
                'module' => $module,
                'auditable_type' => $subject ? $subject::class : null,
                'auditable_id' => $subject?->getKey(),
                'description' => $description,
                'old_values' => $this->redact($old),
                'new_values' => $this->redact($new),
                'ip_address' => $this->ip(),
                'user_agent' => $this->userAgent(),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to write an audit log entry.', [
                'action' => $action,
                'module' => $module,
                'subject' => $subject ? $subject::class.'#'.$subject->getKey() : null,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Strips values that must never be stored, whether or not the calling
     * model remembered to exclude them.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function redact(?array $values): ?array
    {
        if ($values === null || $values === []) {
            return null;
        }

        foreach (self::ALWAYS_REDACTED as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }

    /**
     * There is no client behind a console command or a seeder, so these stay
     * null there.
     *
     * The check is for a real remote address rather than runningInConsole(),
     * which is also true under PHPUnit — using it would leave the origin
     * columns permanently untested.
     */
    private function ip(): ?string
    {
        return $this->fromHttpRequest() ? request()->ip() : null;
    }

    private function userAgent(): ?string
    {
        if (! $this->fromHttpRequest()) {
            return null;
        }

        // The column is TEXT, but a hostile client can send a very long header.
        return mb_substr((string) request()->userAgent(), 0, 512) ?: null;
    }

    private function fromHttpRequest(): bool
    {
        return app()->bound('request') && request()->server->has('REMOTE_ADDR');
    }
}
