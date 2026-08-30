<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Issues the monthly invoices.
 *
 * Safe to run repeatedly. The cycle is found or opened rather than created
 * blindly, and a subscription already invoiced for the period is skipped —
 * backed by a unique index on (subscription_id, billing_period_start), so two
 * runs racing each other cannot both succeed.
 */
class GenerateInvoices extends Command
{
    protected $signature = 'billing:generate-invoices
                            {--month= : Bill a specific month as YYYY-MM, defaulting to the current one}
                            {--dry-run : Report what would be issued without issuing anything}';

    protected $description = 'Generate invoices for the billing cycle covering a month';

    public function handle(BillingService $billing): int
    {
        try {
            $month = $this->resolveMonth();
        } catch (Throwable) {
            $this->error('Could not read --month. Use the YYYY-MM format, for example 2026-08.');

            return self::INVALID;
        }

        $cycle = $billing->cycleFor($month);

        $this->line("Billing cycle: <info>{$cycle->name}</info> ({$cycle->period_start->toDateString()} to {$cycle->period_end->toDateString()})");

        if ($this->option('dry-run')) {
            return $this->reportDryRun($billing, $cycle);
        }

        $summary = $billing->generate($cycle);

        $this->table(
            ['Issued', 'Skipped', 'Failed'],
            [[$summary['created'], $summary['skipped'], $summary['failed']]],
        );

        foreach ($summary['errors'] as $error) {
            $this->warn($error);
        }

        // Written to the application log as well: a scheduled run has no one
        // watching its console output.
        Log::info('Scheduled invoice generation finished.', [
            'cycle' => $cycle->name,
            'created' => $summary['created'],
            'skipped' => $summary['skipped'],
            'failed' => $summary['failed'],
        ]);

        // A partial failure is still a failure as far as the scheduler is
        // concerned, so it shows up rather than passing quietly.
        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function reportDryRun(BillingService $billing, $cycle): int
    {
        $billable = $billing->billableSubscriptions($cycle);
        $pending = $billable->reject(fn ($subscription) => $billing->alreadyInvoiced($subscription, $cycle));

        $this->info(sprintf(
            'Dry run: %d subscription(s) billable, %d already invoiced, %d would be issued.',
            $billable->count(),
            $billable->count() - $pending->count(),
            $pending->count(),
        ));

        foreach ($pending as $subscription) {
            $this->line("  {$subscription->subscription_code}  {$subscription->customer?->full_name}");
        }

        return self::SUCCESS;
    }

    private function resolveMonth(): Carbon
    {
        $month = $this->option('month');

        return $month ? Carbon::createFromFormat('Y-m', $month)->startOfMonth() : Carbon::now();
    }
}
