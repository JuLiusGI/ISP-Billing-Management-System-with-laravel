<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Development billing history: a few months of invoices with a realistic mix
 * of paid, part-paid and overdue.
 *
 * Deliberately drives BillingService and PaymentService rather than inserting
 * rows. Sample data built by hand tends to drift from what the application
 * itself would produce, and then the reports read plausibly while proving
 * nothing. Going through the services means the seeded figures reconcile the
 * same way real ones do.
 *
 * Skipped entirely once any invoice exists.
 */
class BillingHistorySeeder extends Seeder
{
    private const MONTHS = 3;

    public function run(): void
    {
        if (Invoice::withTrashed()->exists()) {
            return;
        }

        $billing = app(BillingService::class);
        $payments = app(PaymentService::class);
        $actor = User::where('email', env('SEED_ADMIN_EMAIL', 'admin@example.com'))->first();

        foreach (range(self::MONTHS - 1, 0) as $monthsAgo) {
            $cycle = $billing->cycleFor(Carbon::now()->subMonths($monthsAgo), $actor);
            $billing->generate($cycle, $actor);
        }

        $billing->markOverdueInvoices();

        // Settle roughly two thirds, and part-pay a few of the rest, so the
        // ageing and outstanding reports have something to show.
        $open = Invoice::outstanding()->with('customer')->get();
        $toSettle = $open->take((int) floor($open->count() * 0.6));
        $toPartPay = $open->slice($toSettle->count())->take(4);

        foreach ($toSettle as $invoice) {
            $this->pay($payments, $invoice, (string) $invoice->balance_due, $actor);
        }

        foreach ($toPartPay as $invoice) {
            $half = bcdiv((string) $invoice->balance_due, '2', 2);

            if (bccomp($half, '0', 2) === 1) {
                $this->pay($payments, $invoice, $half, $actor);
            }
        }
    }

    private function pay(PaymentService $payments, Invoice $invoice, string $amount, ?User $actor): void
    {
        $payments->record(
            $invoice->customer,
            [
                'amount' => $amount,
                'payment_date' => $invoice->invoice_date->copy()
                    ->addDays(fake()->numberBetween(1, 12))
                    ->min(Carbon::now())
                    ->toDateString(),
                'payment_method' => fake()->randomElement(['cash', 'gcash', 'bank_transfer']),
            ],
            [$invoice->id => $amount],
            $actor,
        );
    }
}
