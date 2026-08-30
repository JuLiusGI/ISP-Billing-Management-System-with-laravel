<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The scheduler's view of service status: which lines should come down for
 * non-payment, and which have simply run out.
 *
 * Kept out of the commands so the same rules can be applied from a controller,
 * a test, or a future API without being reimplemented, and so the commands stay
 * thin enough to read.
 *
 * Every change goes through SubscriptionService::changeStatus, which means the
 * state machine, the service status log, the audit trail, the customer's
 * connection status and the provisioning hook all still apply. Nothing here
 * writes a status directly.
 */
class ServiceStatusAutomation
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Suspends active services whose customer has an invoice overdue by at
     * least the configured threshold.
     *
     * Returns a summary rather than a count so a caller can report what it
     * skipped and why, and so a dry run can describe the same work.
     *
     * @return array{eligible: int, suspended: int, skipped: int, failed: int, subscriptions: array<int, string>, errors: array<int, string>}
     */
    public function suspendOverdue(bool $dryRun = false): array
    {
        $summary = [
            'eligible' => 0, 'suspended' => 0, 'skipped' => 0, 'failed' => 0,
            'subscriptions' => [], 'errors' => [],
        ];

        // The switch is checked here rather than in the command, so any caller
        // gets the same refusal.
        if (! $this->settings->autoSuspendEnabled()) {
            return $summary;
        }

        $threshold = $this->settings->suspendAfterDaysOverdue();
        $candidates = $this->overdueBeyond($threshold);

        $summary['eligible'] = $candidates->count();

        foreach ($candidates as $subscription) {
            if ($dryRun) {
                $summary['subscriptions'][] = $subscription->subscription_code;

                continue;
            }

            try {
                $this->subscriptions->changeStatus(
                    $subscription,
                    SubscriptionStatus::Suspended,
                    "Automatically suspended: invoice overdue by {$threshold} days or more",
                    null,
                    automatic: true,
                );

                $summary['suspended']++;
                $summary['subscriptions'][] = $subscription->subscription_code;
            } catch (DomainException $e) {
                // The line moved between the query and the write.
                $summary['skipped']++;
            } catch (Throwable $e) {
                $summary['failed']++;
                $summary['errors'][] = "{$subscription->subscription_code}: {$e->getMessage()}";

                Log::error('Automatic suspension failed.', [
                    'subscription' => $subscription->subscription_code,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /**
     * Moves services past their expiration date to Expired.
     *
     * Unlike suspension this is not configurable: an expiry date that has
     * passed is a fact, not a policy decision.
     *
     * @return array{eligible: int, expired: int, skipped: int, failed: int, subscriptions: array<int, string>, errors: array<int, string>}
     */
    public function expireLapsed(bool $dryRun = false): array
    {
        $summary = [
            'eligible' => 0, 'expired' => 0, 'skipped' => 0, 'failed' => 0,
            'subscriptions' => [], 'errors' => [],
        ];

        $candidates = Subscription::query()
            ->with('customer')
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Suspended->value])
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<', now()->toDateString())
            ->get();

        $summary['eligible'] = $candidates->count();

        foreach ($candidates as $subscription) {
            if ($dryRun) {
                $summary['subscriptions'][] = $subscription->subscription_code;

                continue;
            }

            try {
                $this->subscriptions->changeStatus(
                    $subscription,
                    SubscriptionStatus::Expired,
                    'Automatically expired: past its expiration date',
                    null,
                    automatic: true,
                );

                $summary['expired']++;
                $summary['subscriptions'][] = $subscription->subscription_code;
            } catch (DomainException) {
                $summary['skipped']++;
            } catch (Throwable $e) {
                $summary['failed']++;
                $summary['errors'][] = "{$subscription->subscription_code}: {$e->getMessage()}";
            }
        }

        return $summary;
    }

    /**
     * Active services whose customer has an outstanding invoice at least
     * $days past its due date.
     *
     * Matched on the customer rather than the subscription: a customer with
     * two lines and one unpaid invoice is behind on their account, and the
     * invoice does not necessarily name which line it was for.
     *
     * @return Collection<int, Subscription>
     */
    public function overdueBeyond(int $days)
    {
        $cutoff = now()->subDays($days)->toDateString();

        return Subscription::query()
            ->with('customer')
            ->where('status', SubscriptionStatus::Active)
            ->whereHas('customer.invoices', function ($query) use ($cutoff): void {
                $query->whereIn('status', array_map(
                    fn (InvoiceStatus $status) => $status->value,
                    InvoiceStatus::outstanding()
                ))
                    ->where('balance_due', '>', 0)
                    ->whereDate('due_date', '<=', $cutoff);
            })
            ->get();
    }
}
