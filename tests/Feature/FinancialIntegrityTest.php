<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DashboardService;
use App\Services\FinancialReportService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\ReceiptService;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Cross-cutting financial integrity.
 *
 * The per-module suites each check their own arithmetic. These check the
 * invariants that span modules and that no single suite would notice breaking:
 * that the stored figures still agree with the allocations after a sequence of
 * operations, and that the dashboard and the reports — which compute the same
 * numbers by different routes — return the same answer.
 */
class FinancialIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(SystemSettingSeeder::class);
        $this->seed(ExpenseCategorySeeder::class);

        Notification::fake();
    }

    /**
     * The core invariant: every invoice's stored amount_paid equals the sum of
     * its completed allocations, and balance_due is the remainder floored at
     * zero.
     *
     * The stored columns exist so listings and reports are fast; allocations
     * are the truth. If those two ever diverge, every figure in the system is
     * suspect.
     */
    private function assertBooksBalance(string $context = ''): void
    {
        foreach (Invoice::with('allocations.payment')->get() as $invoice) {
            $allocated = $invoice->allocations
                ->filter(fn (PaymentAllocation $a) => $a->payment?->status === PaymentStatus::Completed)
                ->reduce(fn (string $carry, PaymentAllocation $a) => bcadd($carry, (string) $a->amount, 2), '0.00');

            // Cancelled and void invoices carry no balance by definition.
            if (in_array($invoice->status, [InvoiceStatus::Cancelled, InvoiceStatus::Void], true)) {
                $this->assertSame('0.00', (string) $invoice->balance_due,
                    "{$context}: cancelled invoice {$invoice->invoice_number} should carry no balance.");

                continue;
            }

            $this->assertSame($allocated, (string) $invoice->amount_paid,
                "{$context}: {$invoice->invoice_number} amount_paid disagrees with its allocations.");

            $expected = bcsub((string) $invoice->total_amount, $allocated, 2);
            $expected = bccomp($expected, '0', 2) === -1 ? '0.00' : $expected;

            $this->assertSame($expected, (string) $invoice->balance_due,
                "{$context}: {$invoice->invoice_number} balance_due is not total less allocations.");
        }
    }

    // -----------------------------------------------------------------
    // A full lifecycle, checked at every step
    // -----------------------------------------------------------------

    public function test_the_books_balance_through_a_whole_customer_lifecycle(): void
    {
        $actor = User::factory()->create();
        $customer = Customer::factory()->create();
        $subscription = Subscription::factory()->for($customer)->create([
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subYear(),
            'monthly_rate' => 1500,
            'discount_amount' => 0,
            'installation_fee' => 0,
        ]);

        $billing = app(BillingService::class);
        $payments = app(PaymentService::class);

        // 1. Bill three months.
        foreach ([2, 1, 0] as $back) {
            $billing->generate($billing->cycleFor(now()->subMonths($back), $actor), $actor);
        }
        $this->assertBooksBalance('after generation');
        $this->assertSame(3, Invoice::count());

        $invoices = Invoice::orderBy('id')->get();

        // 2. Part-pay the first.
        $payments->record($customer, ['amount' => '500.00', 'payment_method' => 'cash'],
            [$invoices[0]->id => '500.00'], $actor);
        $this->assertBooksBalance('after a part payment');
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoices[0]->refresh()->status);

        // 3. Settle the rest of it.
        $payments->record($customer, ['amount' => '1000.00', 'payment_method' => 'gcash'],
            [$invoices[0]->id => '1000.00'], $actor);
        $this->assertBooksBalance('after settling');
        $this->assertSame(InvoiceStatus::Paid, $invoices[0]->refresh()->status);

        // 4. One payment across the remaining two.
        $spread = $payments->record($customer, ['amount' => '3000.00', 'payment_method' => 'bank_transfer'], [
            $invoices[1]->id => '1500.00',
            $invoices[2]->id => '1500.00',
        ], $actor);
        $this->assertBooksBalance('after a spread payment');

        // 5. Reverse it — both invoices must reopen.
        $payments->reverse($spread, 'Bounced transfer', $actor);
        $this->assertBooksBalance('after a reversal');

        $this->assertSame('1500.00', (string) $invoices[1]->refresh()->balance_due);
        $this->assertSame('1500.00', (string) $invoices[2]->refresh()->balance_due);

        // 6. Pay again and issue a receipt.
        $final = $payments->record($customer, ['amount' => '3000.00', 'payment_method' => 'cash'], [
            $invoices[1]->id => '1500.00',
            $invoices[2]->id => '1500.00',
        ], $actor);
        app(ReceiptService::class)->issue($final, $actor);

        $this->assertBooksBalance('after re-payment');
        $this->assertSame('0.00', $customer->refresh()->outstandingBalance());
        $this->assertNotNull($final->refresh()->receipt);
    }

    public function test_an_overpayment_leaves_credit_without_unbalancing_the_books(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->for($customer)->ofAmount(1000)->create([
            'status' => InvoiceStatus::Unpaid,
        ]);

        $payment = app(PaymentService::class)->record(
            $customer,
            ['amount' => '2500.00', 'payment_method' => 'cash'],
            [$invoice->id => '1000.00'],
        );

        $this->assertBooksBalance('after an overpayment');

        // The excess sits on the payment as credit, not on the invoice.
        $this->assertSame('1500.00', $payment->refresh()->unallocatedAmount());
        $this->assertSame('0.00', (string) $invoice->refresh()->balance_due);
        $this->assertSame('0.00', $customer->refresh()->outstandingBalance());
    }

    public function test_cancelling_an_unpaid_invoice_removes_it_from_receivables_everywhere(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->for($customer)->ofAmount(2000)->create([
            'status' => InvoiceStatus::Unpaid,
        ]);

        $this->assertSame('2000.00', $customer->outstandingBalance());

        app(InvoiceService::class)->cancel($invoice, 'Issued in error', User::factory()->create());

        $this->assertBooksBalance('after cancellation');

        // Customer balance, dashboard and report must all agree it is gone.
        $this->assertSame('0.00', $customer->refresh()->outstandingBalance());
        $this->assertSame('0.00', app(DashboardService::class)->billingStats()['totalOutstanding']);
        $this->assertSame('0.00', app(FinancialReportService::class)->outstanding()['total']);
    }

    // -----------------------------------------------------------------
    // The dashboard and the reports must agree
    // -----------------------------------------------------------------

    public function test_the_dashboard_and_the_outstanding_report_agree(): void
    {
        $this->buildMixedLedger();

        $this->assertSame(
            app(DashboardService::class)->billingStats()['totalOutstanding'],
            app(FinancialReportService::class)->outstanding()['total'],
            'Two code paths computing receivables must not disagree.'
        );
    }

    public function test_the_dashboard_and_the_overdue_report_agree(): void
    {
        $this->buildMixedLedger();

        $this->assertSame(
            app(DashboardService::class)->billingStats()['totalOverdue'],
            app(FinancialReportService::class)->overdue()['total'],
        );
    }

    public function test_the_dashboard_and_the_revenue_report_agree_for_this_month(): void
    {
        $this->buildMixedLedger();

        $reported = app(FinancialReportService::class)
            ->revenue(now()->startOfMonth(), now()->endOfMonth())['total'];

        $this->assertSame(
            app(DashboardService::class)->financialStats()['revenueThisMonth'],
            $reported,
        );
    }

    public function test_the_ageing_buckets_sum_to_the_receivable_total(): void
    {
        $this->buildMixedLedger();

        $report = app(FinancialReportService::class)->outstanding();

        $sum = collect($report['buckets'])->reduce(
            fn (string $carry, array $bucket) => bcadd($carry, $bucket['total'] ?: '0', 2),
            '0.00'
        );

        $this->assertSame($report['total'], $sum,
            'An ageing report whose buckets do not add up to its own total is worthless.');
    }

    public function test_the_overdue_buckets_sum_to_the_overdue_total(): void
    {
        $this->buildMixedLedger();

        $report = app(FinancialReportService::class)->overdue();

        $sum = $report['buckets']->reduce(
            fn (string $carry, $row) => bcadd($carry, (string) $row->total, 2),
            '0.00'
        );

        $this->assertSame($report['total'], $sum);
    }

    // -----------------------------------------------------------------
    // Reversed money must disappear from every view of it
    // -----------------------------------------------------------------

    public function test_a_reversal_is_excluded_from_revenue_dashboard_and_customer_balance_alike(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->for($customer)->ofAmount(1200)->create([
            'status' => InvoiceStatus::Unpaid,
        ]);

        $payments = app(PaymentService::class);
        $payment = $payments->record($customer, [
            'amount' => '1200.00', 'payment_method' => 'cash', 'payment_date' => now()->toDateString(),
        ], [$invoice->id => '1200.00']);

        $this->assertSame('1200.00', app(DashboardService::class)->financialStats()['revenueThisMonth']);

        $payments->reverse($payment, 'Cheque bounced', User::factory()->create());

        $this->assertBooksBalance('after reversal');

        // Gone from revenue, back on the customer's balance, back in receivables.
        $this->assertSame('0.00', app(DashboardService::class)->financialStats()['revenueThisMonth']);
        $this->assertSame('0.00', app(FinancialReportService::class)
            ->revenue(now()->startOfMonth(), now()->endOfMonth())['total']);
        $this->assertSame('1200.00', $customer->refresh()->outstandingBalance());
        $this->assertSame('1200.00', app(FinancialReportService::class)->outstanding()['total']);
    }

    // -----------------------------------------------------------------
    // Concurrency
    // -----------------------------------------------------------------

    public function test_two_allocations_against_one_invoice_cannot_exceed_its_balance(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->for($customer)->ofAmount(1000)->create([
            'status' => InvoiceStatus::Unpaid,
        ]);

        $payments = app(PaymentService::class);

        $payments->record($customer, ['amount' => '600.00', 'payment_method' => 'cash'],
            [$invoice->id => '600.00']);

        // The second cashier's allocation is validated against the balance as
        // it stands now, not as it stood when their page loaded.
        $this->expectException(\DomainException::class);

        $payments->record($customer, ['amount' => '600.00', 'payment_method' => 'cash'],
            [$invoice->id => '600.00']);
    }

    public function test_the_invoice_is_locked_while_its_balance_is_read_and_written(): void
    {
        // Guards the mechanism rather than the outcome: without the row lock,
        // the check above becomes a race two cashiers can both pass.
        $service = file_get_contents(app_path('Services/PaymentService.php'));

        $this->assertStringContainsString('lockForUpdate()', $service);
    }

    // -----------------------------------------------------------------

    /** A ledger with paid, part-paid, unpaid, overdue and cancelled invoices. */
    private function buildMixedLedger(): void
    {
        $payments = app(PaymentService::class);

        // Settled.
        $a = Invoice::factory()->ofAmount(1000)->create([
            'status' => InvoiceStatus::Unpaid, 'due_date' => now()->addWeek(),
        ]);
        $payments->record($a->customer, [
            'amount' => '1000.00', 'payment_method' => 'cash', 'payment_date' => now()->toDateString(),
        ], [$a->id => '1000.00']);

        // Part paid, overdue.
        $b = Invoice::factory()->ofAmount(2000)->create([
            'status' => InvoiceStatus::Unpaid, 'due_date' => now()->subDays(20),
        ]);
        $payments->record($b->customer, [
            'amount' => '500.00', 'payment_method' => 'gcash', 'payment_date' => now()->toDateString(),
        ], [$b->id => '500.00']);

        // Untouched, various ages.
        Invoice::factory()->ofAmount(750)->create([
            'status' => InvoiceStatus::Unpaid, 'due_date' => now()->subDays(75),
        ]);
        Invoice::factory()->ofAmount(300)->create([
            'status' => InvoiceStatus::Unpaid, 'due_date' => now()->addDays(10),
        ]);

        // Cancelled: must count nowhere.
        Invoice::factory()->cancelled()->create([
            'total_amount' => 9999, 'amount_paid' => 0, 'balance_due' => 0,
            'due_date' => now()->subDays(40),
        ]);

        app(BillingService::class)->markOverdueInvoices();
    }
}
