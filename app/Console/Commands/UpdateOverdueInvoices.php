<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Moves outstanding invoices past their due date to Overdue.
 *
 * Idempotent: an invoice already marked overdue no longer matches the query,
 * and anything settled, cancelled or void is excluded by the status filter, so
 * running this twice in a day changes nothing the second time.
 */
class UpdateOverdueInvoices extends Command
{
    protected $signature = 'billing:update-overdue';

    protected $description = 'Mark outstanding invoices past their due date as overdue';

    public function handle(BillingService $billing): int
    {
        $marked = $billing->markOverdueInvoices();

        $this->info($marked === 0
            ? 'No invoices became overdue.'
            : sprintf('%d invoice(s) marked overdue.', $marked));

        if ($marked > 0) {
            Log::info('Scheduled overdue sweep finished.', ['marked' => $marked]);
        }

        return self::SUCCESS;
    }
}
