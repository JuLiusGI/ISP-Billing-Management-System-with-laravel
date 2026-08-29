<?php

namespace Tests\Feature;

use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Subscription;
use App\Services\BillingService;
use App\Services\InvoiceService;
use App\Services\SettingsService;
use Database\Seeders\SystemSettingSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingEngineTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $invoices;

    private BillingService $billing;

    private SettingsService $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemSettingSeeder::class);

        $this->settings = app(SettingsService::class);
        $this->settings->flush();

        $this->invoices = app(InvoiceService::class);
        $this->billing = app(BillingService::class);
    }

    // -----------------------------------------------------------------
    // Invoice arithmetic
    // -----------------------------------------------------------------

    public function test_totals_are_derived_from_the_line_items(): void
    {
        $invoice = $this->invoices->create(Customer::factory()->create(), [
            ['description' => 'Monthly service', 'unit_price' => '1499.00'],
            ['description' => 'Installation', 'unit_price' => '1500.00'],
        ]);

        $this->assertSame('2999.00', $invoice->subtotal);
        $this->assertSame('0.00', $invoice->discount_total);
        $this->assertSame('2999.00', $invoice->total_amount);
        $this->assertSame('2999.00', $invoice->balance_due);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
    }

    public function test_a_line_total_is_quantity_times_price_less_its_discount(): void
    {
        $invoice = $this->invoices->create(Customer::factory()->create(), [
            ['description' => 'Bandwidth', 'quantity' => 3, 'unit_price' => '250.00', 'discount_amount' => '50.00'],
        ]);

        $item = $invoice->items->first();

        $this->assertSame('700.00', $item->line_total);
        $this->assertSame('750.00', $invoice->subtotal);
        $this->assertSame('50.00', $invoice->discount_total);
        $this->assertSame('700.00', $invoice->total_amount);
    }

    public function test_an_invoice_level_discount_is_applied_on_top_of_item_discounts(): void
    {
        $invoice = $this->invoices->create(
            Customer::factory()->create(),
            [['description' => 'Monthly service', 'unit_price' => '1500.00', 'discount_amount' => '100.00']],
            ['discount_total' => '200.00'],
        );

        $this->assertSame('1500.00', $invoice->subtotal);
        $this->assertSame('300.00', $invoice->discount_total);
        $this->assertSame('1200.00', $invoice->total_amount);
    }

    public function test_additional_charges_are_added_to_the_total(): void
    {
        $invoice = $this->invoices->create(
            Customer::factory()->create(),
            [['description' => 'Monthly service', 'unit_price' => '1000.00']],
            ['charges_total' => '150.50'],
        );

        $this->assertSame('150.50', $invoice->charges_total);
        $this->assertSame('1150.50', $invoice->total_amount);
    }

    public function test_money_arithmetic_does_not_drift(): void
    {
        // Three lines that a float would round badly.
        $invoice = $this->invoices->create(Customer::factory()->create(), [
            ['description' => 'A', 'quantity' => 3, 'unit_price' => '1499.99'],
            ['description' => 'B', 'unit_price' => '0.10'],
            ['description' => 'C', 'unit_price' => '0.20'],
        ]);

        $this->assertSame('4500.27', $invoice->subtotal);
        $this->assertSame('4500.27', $invoice->total_amount);
    }

    public function test_tax_is_only_applied_when_enabled(): void
    {
        $customer = Customer::factory()->create();

        $untaxed = $this->invoices->create($customer, [
            ['description' => 'Monthly service', 'unit_price' => '1000.00'],
        ]);
        $this->assertSame('0.00', $untaxed->tax_total);
        $this->assertSame('1000.00', $untaxed->total_amount);

        $this->settings->set('billing.tax_enabled', true);
        $this->settings->set('billing.tax_rate', '12.00');

        $taxed = app(InvoiceService::class)->create($customer, [
            ['description' => 'Monthly service', 'unit_price' => '1000.00'],
        ]);

        $this->assertSame('120.00', $taxed->tax_total);
        $this->assertSame('1120.00', $taxed->total_amount);
    }

    public function test_tax_is_charged_on_the_discounted_amount_not_the_subtotal(): void
    {
        $this->settings->set('billing.tax_enabled', true);
        $this->settings->set('billing.tax_rate', '10.00');

        $invoice = app(InvoiceService::class)->create(
            Customer::factory()->create(),
            [['description' => 'Monthly service', 'unit_price' => '1000.00']],
            ['discount_total' => '200.00'],
        );

        $this->assertSame('80.00', $invoice->tax_total);
        $this->assertSame('880.00', $invoice->total_amount);
    }

    public function test_a_discount_larger_than_the_bill_never_produces_a_negative_total(): void
    {
        $invoice = $this->invoices->create(
            Customer::factory()->create(),
            [['description' => 'Monthly service', 'unit_price' => '500.00']],
            ['discount_total' => '900.00'],
        );

        $this->assertSame('0.00', $invoice->total_amount);
        $this->assertSame('0.00', $invoice->balance_due);
    }

    public function test_an_invoice_needs_at_least_one_line(): void
    {
        $this->expectException(DomainException::class);

        $this->invoices->create(Customer::factory()->create(), []);
    }

    // -----------------------------------------------------------------
    // Numbering and dates, driven by settings
    // -----------------------------------------------------------------

    public function test_the_invoice_number_uses_the_configured_prefix(): void
    {
        $this->settings->set('billing.invoice_prefix', 'BILL');

        $invoice = app(InvoiceService::class)->create(Customer::factory()->create(), [
            ['description' => 'Monthly service', 'unit_price' => '100.00'],
        ]);

        $this->assertMatchesRegularExpression('/^BILL-\d{4}-\d{6}$/', $invoice->invoice_number);
    }

    public function test_invoice_numbers_do_not_repeat(): void
    {
        $customer = Customer::factory()->create();

        $numbers = collect(range(1, 8))->map(fn () => $this->invoices->create($customer, [
            ['description' => 'Monthly service', 'unit_price' => '100.00'],
        ])->invoice_number);

        $this->assertCount(8, $numbers->unique());
    }

    public function test_the_due_date_is_the_issue_date_plus_the_grace_period(): void
    {
        $this->settings->set('billing.grace_period_days', 10);

        $invoice = app(InvoiceService::class)->create(
            Customer::factory()->create(),
            [['description' => 'Monthly service', 'unit_price' => '100.00']],
            ['invoice_date' => '2026-08-01'],
        );

        $this->assertSame('2026-08-11', $invoice->due_date->format('Y-m-d'));
    }

    // -----------------------------------------------------------------
    // Generation
    // -----------------------------------------------------------------

    private function activeSubscription(array $overrides = []): Subscription
    {
        $plan = InternetPlan::factory()->priced(1499)->create();

        return Subscription::factory()->forPlan($plan)->create(array_replace([
            'start_date' => '2026-01-01',
            'billing_day' => 5,
            'monthly_rate' => '1499.00',
            'installation_fee' => '0.00',
            'discount_amount' => '0.00',
        ], $overrides));
    }

    public function test_generation_issues_one_invoice_per_active_subscription(): void
    {
        $this->activeSubscription();
        $this->activeSubscription();

        $cycle = $this->billing->cycleFor(Carbon::parse('2026-08-15'));
        $summary = $this->billing->generate($cycle);

        $this->assertSame(2, $summary['created']);
        $this->assertSame(0, $summary['skipped']);
        $this->assertSame(2, Invoice::count());
    }

    public function test_generation_is_safe_to_run_twice(): void
    {
        $this->activeSubscription();
        $cycle = $this->billing->cycleFor(Carbon::parse('2026-08-15'));

        $first = $this->billing->generate($cycle);
        $second = $this->billing->generate($cycle);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, Invoice::count());
    }

    public function test_only_active_subscriptions_are_billed(): void
    {
        $this->activeSubscription();
        Subscription::factory()->suspended()->create(['start_date' => '2026-01-01']);
        Subscription::factory()->pending()->create(['start_date' => '2026-01-01']);
        Subscription::factory()->cancelled()->create(['start_date' => '2026-01-01']);

        $summary = $this->billing->generate($this->billing->cycleFor(Carbon::parse('2026-08-15')));

        $this->assertSame(1, $summary['created']);
    }

    public function test_a_subscription_starting_after_the_period_is_not_billed(): void
    {
        $this->activeSubscription(['start_date' => '2026-12-01']);

        $summary = $this->billing->generate($this->billing->cycleFor(Carbon::parse('2026-08-15')));

        $this->assertSame(0, $summary['created']);
    }

    public function test_the_invoice_carries_the_subscriptions_agreed_rate_and_discount(): void
    {
        $this->activeSubscription(['monthly_rate' => '1200.00', 'discount_amount' => '200.00']);

        $this->billing->generate($this->billing->cycleFor(Carbon::parse('2026-08-15')));

        $invoice = Invoice::first();

        $this->assertSame('1200.00', $invoice->subtotal);
        $this->assertSame('200.00', $invoice->discount_total);
        $this->assertSame('1000.00', $invoice->total_amount);
    }

    public function test_the_installation_fee_is_billed_once_only(): void
    {
        $this->activeSubscription(['installation_fee' => '1500.00']);

        $this->billing->generate($this->billing->cycleFor(Carbon::parse('2026-08-15')));
        $first = Invoice::latest('id')->first();

        $this->billing->generate($this->billing->cycleFor(Carbon::parse('2026-09-15')));
        $second = Invoice::latest('id')->first();

        $this->assertSame('2999.00', $first->total_amount);
        $this->assertCount(2, $first->items);
        $this->assertTrue($first->items->contains('item_type', InvoiceItemType::Installation));

        $this->assertSame('1499.00', $second->total_amount);
        $this->assertCount(1, $second->items);
    }

    public function test_the_billing_day_is_clamped_into_short_months(): void
    {
        $this->activeSubscription(['billing_day' => 31]);

        // February 2026 has 28 days.
        $this->billing->generate($this->billing->cycleFor(Carbon::parse('2026-02-10')));

        $this->assertSame('2026-02-28', Invoice::first()->invoice_date->format('Y-m-d'));
    }

    public function test_the_billing_day_is_used_when_the_month_is_long_enough(): void
    {
        $this->activeSubscription(['billing_day' => 15]);

        $this->billing->generate($this->billing->cycleFor(Carbon::parse('2026-08-01')));

        $this->assertSame('2026-08-15', Invoice::first()->invoice_date->format('Y-m-d'));
    }

    public function test_opening_the_same_month_twice_reuses_the_cycle(): void
    {
        $first = $this->billing->cycleFor(Carbon::parse('2026-08-01'));
        $second = $this->billing->cycleFor(Carbon::parse('2026-08-28'));

        $this->assertTrue($first->is($second));
        $this->assertSame(1, BillingCycle::count());
    }

    public function test_the_cycle_records_when_and_by_whom_it_was_generated(): void
    {
        $this->activeSubscription();
        $cycle = $this->billing->cycleFor(Carbon::parse('2026-08-15'));

        $this->billing->generate($cycle);

        $this->assertNotNull($cycle->refresh()->generated_at);
        $this->assertSame('closed', $cycle->status->value);
    }

    // -----------------------------------------------------------------
    // Balances after payment
    // -----------------------------------------------------------------

    private function allocate(Invoice $invoice, string $amount): void
    {
        $payment = Payment::factory()->for($invoice->customer)->create([
            'amount' => $amount, 'allocated_amount' => $amount,
        ]);

        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
        ]);
    }

    public function test_a_part_payment_leaves_the_invoice_partially_paid(): void
    {
        $invoice = $this->invoices->create(Customer::factory()->create(), [
            ['description' => 'Monthly service', 'unit_price' => '1000.00'],
        ]);

        $this->allocate($invoice, '400.00');
        $this->invoices->recalculate($invoice->refresh());

        $invoice->refresh();
        $this->assertSame('400.00', $invoice->amount_paid);
        $this->assertSame('600.00', $invoice->balance_due);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
    }

    public function test_settling_the_full_amount_marks_the_invoice_paid(): void
    {
        $invoice = $this->invoices->create(Customer::factory()->create(), [
            ['description' => 'Monthly service', 'unit_price' => '1000.00'],
        ]);

        $this->allocate($invoice, '1000.00');
        $this->invoices->recalculate($invoice->refresh());

        $invoice->refresh();
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_an_overpayment_does_not_drive_the_balance_negative(): void
    {
        $invoice = $this->invoices->create(Customer::factory()->create(), [
            ['description' => 'Monthly service', 'unit_price' => '1000.00'],
        ]);

        $this->allocate($invoice, '1500.00');
        $this->invoices->recalculate($invoice->refresh());

        $invoice->refresh();
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_a_reversed_payment_stops_counting_toward_the_balance(): void
    {
        $invoice = $this->invoices->create(Customer::factory()->create(), [
            ['description' => 'Monthly service', 'unit_price' => '1000.00'],
        ]);

        $payment = Payment::factory()->for($invoice->customer)->reversed()->create(['amount' => '1000.00']);
        PaymentAllocation::create([
            'payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => '1000.00',
        ]);

        $this->invoices->recalculate($invoice->refresh());

        $invoice->refresh();
        $this->assertSame('0.00', $invoice->amount_paid);
        $this->assertSame('1000.00', $invoice->balance_due);
    }

    // -----------------------------------------------------------------
    // Overdue detection
    // -----------------------------------------------------------------

    public function test_overdue_marking_only_touches_unsettled_invoices_past_due(): void
    {
        Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid, 'due_date' => now()->subDays(3), 'balance_due' => 500,
        ]);
        Invoice::factory()->create([
            'status' => InvoiceStatus::PartiallyPaid, 'due_date' => now()->subDay(), 'balance_due' => 200,
        ]);
        Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid, 'due_date' => now()->addDays(5), 'balance_due' => 500,
        ]);
        Invoice::factory()->create([
            'status' => InvoiceStatus::Paid, 'due_date' => now()->subDays(30), 'balance_due' => 0,
        ]);
        Invoice::factory()->cancelled()->create(['due_date' => now()->subDays(30)]);

        $this->assertSame(2, $this->billing->markOverdueInvoices());
        $this->assertSame(2, Invoice::where('status', InvoiceStatus::Overdue)->count());
    }

    public function test_overdue_marking_is_idempotent(): void
    {
        Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid, 'due_date' => now()->subDays(3), 'balance_due' => 500,
        ]);

        $this->assertSame(1, $this->billing->markOverdueInvoices());
        $this->assertSame(0, $this->billing->markOverdueInvoices());
    }

    // -----------------------------------------------------------------
    // Cancellation
    // -----------------------------------------------------------------

    public function test_cancelling_an_invoice_clears_its_balance_and_records_why(): void
    {
        $invoice = $this->invoices->create(Customer::factory()->create(), [
            ['description' => 'Monthly service', 'unit_price' => '1000.00'],
        ]);

        $this->invoices->cancel($invoice, 'Billed in error');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->status);
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertSame('Billed in error', $invoice->cancellation_reason);
        $this->assertNotNull($invoice->cancelled_at);
    }

    public function test_an_invoice_with_payments_applied_cannot_be_cancelled(): void
    {
        $invoice = $this->invoices->create(Customer::factory()->create(), [
            ['description' => 'Monthly service', 'unit_price' => '1000.00'],
        ]);
        $this->allocate($invoice, '500.00');

        $this->expectException(DomainException::class);

        $this->invoices->cancel($invoice->refresh(), 'Changed my mind');
    }

    public function test_recalculating_a_cancelled_invoice_leaves_it_cancelled(): void
    {
        $invoice = $this->invoices->create(Customer::factory()->create(), [
            ['description' => 'Monthly service', 'unit_price' => '1000.00'],
        ]);
        $this->invoices->cancel($invoice, 'Billed in error');

        $this->invoices->recalculate($invoice->refresh());

        $this->assertSame(InvoiceStatus::Cancelled, $invoice->refresh()->status);
    }
}
