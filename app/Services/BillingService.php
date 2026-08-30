<?php

namespace App\Services;

use App\Enums\BillingCycleStatus;
use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\BillingCycle;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\InvoiceGenerated;
use App\Notifications\InvoiceOverdue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Generates the invoices for a billing period.
 *
 * The generator is written to be safe to run repeatedly. A subscription that
 * already has an invoice for the period is skipped, and the unique index on
 * (subscription_id, billing_period_start) is the backstop for two runs racing
 * each other, so a duplicate is reported as skipped rather than crashing the
 * batch.
 */
class BillingService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly SettingsService $settings,
        private readonly CustomerNotifier $notifier,
    ) {}

    /**
     * Finds or opens the billing cycle covering a month.
     *
     * @param  Carbon  $month  any date inside the intended month
     */
    public function cycleFor(Carbon $month, ?User $actor = null): BillingCycle
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        return BillingCycle::firstOrCreate(
            ['period_start' => $start->toDateString(), 'period_end' => $end->toDateString()],
            [
                'name' => $start->format('F Y'),
                'due_date' => $end->copy()->addDays($this->settings->gracePeriodDays())->toDateString(),
                'status' => BillingCycleStatus::Open,
                'generated_by' => $actor?->id,
            ]
        );
    }

    /**
     * Issues invoices for every billable subscription in the cycle.
     *
     * @return array{created: int, skipped: int, failed: int, invoices: list<int>, errors: list<string>}
     */
    public function generate(BillingCycle $cycle, ?User $actor = null): array
    {
        $summary = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'invoices' => [], 'errors' => []];

        $cycle->update(['status' => BillingCycleStatus::Processing]);

        $this->billableSubscriptions($cycle)->each(function (Subscription $subscription) use ($cycle, $actor, &$summary): void {
            try {
                $invoice = $this->generateFor($subscription, $cycle, $actor);

                if ($invoice === null) {
                    $summary['skipped']++;

                    return;
                }

                $summary['created']++;
                $summary['invoices'][] = $invoice->id;
            } catch (UniqueConstraintViolationException) {
                // A concurrent run beat us to this subscription; not an error.
                $summary['skipped']++;
            } catch (Throwable $e) {
                $summary['failed']++;
                $summary['errors'][] = "{$subscription->subscription_code}: {$e->getMessage()}";
            }
        });

        $cycle->update([
            'status' => BillingCycleStatus::Closed,
            'generated_at' => now(),
            'generated_by' => $actor?->id ?? $cycle->generated_by,
        ]);

        return $summary;
    }

    /**
     * Issues one subscription's invoice for the cycle, or null when it already
     * has one.
     */
    public function generateFor(Subscription $subscription, BillingCycle $cycle, ?User $actor = null): ?Invoice
    {
        if ($this->alreadyInvoiced($subscription, $cycle)) {
            return null;
        }

        $invoiceDate = $this->invoiceDateFor($subscription, $cycle);

        $invoice = DB::transaction(function () use ($subscription, $cycle, $invoiceDate, $actor): Invoice {
            return $this->invoices->create(
                $subscription->customer,
                $this->lineItemsFor($subscription),
                [
                    'subscription_id' => $subscription->id,
                    'billing_cycle_id' => $cycle->id,
                    'billing_period_start' => $cycle->period_start->toDateString(),
                    'billing_period_end' => $cycle->period_end->toDateString(),
                    'invoice_date' => $invoiceDate->toDateString(),
                    'due_date' => $this->invoices->dueDateFor($invoiceDate)->toDateString(),
                    'discount_total' => (string) $subscription->discount_amount,
                ],
                $actor,
            );
        });

        // After commit: the invoice is issued whether or not the customer can
        // be told about it.
        $this->notifier->send($subscription->customer, 'invoice_created', new InvoiceGenerated($invoice));

        return $invoice;
    }

    /**
     * Moves outstanding invoices past their due date to Overdue.
     *
     * Idempotent: invoices already marked overdue are untouched, and anything
     * settled or cancelled is excluded by the status filter.
     */
    public function markOverdueInvoices(): int
    {
        // Read first, then update in one statement. The rows are needed anyway
        // to tell each customer, and a per-model save would turn one UPDATE
        // into one per invoice.
        $invoices = Invoice::query()
            ->with('customer')
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::PartiallyPaid->value])
            ->whereDate('due_date', '<', now()->toDateString())
            ->where('balance_due', '>', 0)
            ->get();

        if ($invoices->isEmpty()) {
            return 0;
        }

        Invoice::whereIn('id', $invoices->modelKeys())->update(['status' => InvoiceStatus::Overdue]);

        foreach ($invoices as $invoice) {
            if ($invoice->customer) {
                $invoice->status = InvoiceStatus::Overdue;
                $this->notifier->send($invoice->customer, 'invoice_overdue', new InvoiceOverdue($invoice));
            }
        }

        return $invoices->count();
    }

    /**
     * Subscriptions eligible for billing in this cycle: active, and started on
     * or before the period ends.
     *
     * @return Collection<int, Subscription>
     */
    public function billableSubscriptions(BillingCycle $cycle)
    {
        return Subscription::query()
            ->with(['customer', 'internetPlan'])
            ->where('status', SubscriptionStatus::Active)
            ->whereDate('start_date', '<=', $cycle->period_end)
            ->get();
    }

    public function alreadyInvoiced(Subscription $subscription, BillingCycle $cycle): bool
    {
        return Invoice::withTrashed()
            ->where('subscription_id', $subscription->id)
            ->whereDate('billing_period_start', $cycle->period_start)
            ->exists();
    }

    /**
     * The subscription's billing day, clamped into the cycle's month.
     *
     * A line billed on the 31st still has to produce an invoice in February,
     * so the day is capped at the last day of the month.
     */
    public function invoiceDateFor(Subscription $subscription, BillingCycle $cycle): Carbon
    {
        $start = $cycle->period_start->copy();
        $day = min($subscription->billing_day, $start->daysInMonth);

        return $start->setDay($day)->startOfDay();
    }

    /**
     * The lines for one subscription's periodic invoice.
     *
     * The installation fee rides on the first invoice only; afterwards the
     * invoice is the monthly service charge alone.
     *
     * @return array<int, array<string, mixed>>
     */
    private function lineItemsFor(Subscription $subscription): array
    {
        $plan = $subscription->internetPlan;

        $items = [[
            'description' => "{$plan->name} — monthly internet service",
            'item_type' => InvoiceItemType::Subscription->value,
            'quantity' => 1,
            'unit_price' => (string) $subscription->monthly_rate,
            'discount_amount' => 0,
        ]];

        if ($this->needsInstallationCharge($subscription)) {
            $items[] = [
                'description' => 'Installation fee',
                'item_type' => InvoiceItemType::Installation->value,
                'quantity' => 1,
                'unit_price' => (string) $subscription->installation_fee,
                'discount_amount' => 0,
            ];
        }

        return $items;
    }

    private function needsInstallationCharge(Subscription $subscription): bool
    {
        if (bccomp((string) $subscription->installation_fee, '0', 2) !== 1) {
            return false;
        }

        return ! Invoice::withTrashed()
            ->where('subscription_id', $subscription->id)
            ->whereHas('items', fn ($q) => $q->where('item_type', InvoiceItemType::Installation))
            ->exists();
    }
}
