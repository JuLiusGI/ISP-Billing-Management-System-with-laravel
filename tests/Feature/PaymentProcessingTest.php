<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Role;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\SettingsService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentProcessingTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $payments;

    private InvoiceService $invoices;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleAndPermissionSeeder::class, SystemSettingSeeder::class]);

        app(SettingsService::class)->flush();
        $this->payments = app(PaymentService::class);
        $this->invoices = app(InvoiceService::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->value('id'));

        return $user->fresh();
    }

    /** An unpaid invoice for the given amount. */
    private function invoiceFor(Customer $customer, string $amount, array $overrides = []): Invoice
    {
        return $this->invoices->create(
            $customer,
            [['description' => 'Monthly service', 'unit_price' => $amount]],
            $overrides,
        );
    }

    // -----------------------------------------------------------------
    // Recording and allocation
    // -----------------------------------------------------------------

    public function test_a_full_payment_settles_its_invoice(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoiceFor($customer, '1000.00');

        $payment = $this->payments->record(
            $customer,
            ['amount' => '1000.00', 'payment_method' => 'cash'],
            [$invoice->id => '1000.00'],
        );

        $invoice->refresh();

        $this->assertSame('1000.00', $payment->allocated_amount);
        $this->assertSame('0.00', $payment->unallocatedAmount());
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_a_part_payment_leaves_a_balance(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoiceFor($customer, '1000.00');

        $this->payments->record(
            $customer,
            ['amount' => '400.00', 'payment_method' => 'cash'],
            [$invoice->id => '400.00'],
        );

        $invoice->refresh();

        $this->assertSame('400.00', $invoice->amount_paid);
        $this->assertSame('600.00', $invoice->balance_due);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
    }

    public function test_several_payments_can_settle_one_invoice(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoiceFor($customer, '1000.00');

        $this->payments->record($customer, ['amount' => '600.00', 'payment_method' => 'cash'], [$invoice->id => '600.00']);
        $this->payments->record($customer, ['amount' => '400.00', 'payment_method' => 'gcash'], [$invoice->id => '400.00']);

        $invoice->refresh();

        $this->assertSame('1000.00', $invoice->amount_paid);
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertCount(2, $invoice->allocations);
    }

    public function test_one_payment_can_settle_several_invoices(): void
    {
        $customer = Customer::factory()->create();
        $first = $this->invoiceFor($customer, '600.00');
        $second = $this->invoiceFor($customer, '400.00');

        $payment = $this->payments->record(
            $customer,
            ['amount' => '1000.00', 'payment_method' => 'bank_transfer'],
            [$first->id => '600.00', $second->id => '400.00'],
        );

        $this->assertSame('1000.00', $payment->allocated_amount);
        $this->assertSame(InvoiceStatus::Paid, $first->refresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $second->refresh()->status);
    }

    public function test_an_overpayment_is_held_as_credit_rather_than_forced_onto_an_invoice(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoiceFor($customer, '500.00');

        $payment = $this->payments->record(
            $customer,
            ['amount' => '800.00', 'payment_method' => 'cash'],
            [$invoice->id => '500.00'],
        );

        $this->assertSame('500.00', $payment->allocated_amount);
        $this->assertSame('300.00', $payment->unallocatedAmount());
        $this->assertSame('0.00', $invoice->refresh()->balance_due);
        $this->assertSame('300.00', $this->payments->availableCreditFor($customer));
    }

    public function test_leftover_credit_can_be_applied_to_a_later_invoice(): void
    {
        $customer = Customer::factory()->create();
        $payment = $this->payments->record($customer, ['amount' => '1000.00', 'payment_method' => 'cash'], []);

        $this->assertSame('1000.00', $payment->unallocatedAmount());

        $invoice = $this->invoiceFor($customer, '400.00');
        $this->payments->allocate($payment, [$invoice->id => '400.00']);

        $this->assertSame('600.00', $payment->refresh()->unallocatedAmount());
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
    }

    public function test_topping_up_the_same_invoice_adjusts_the_existing_allocation(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoiceFor($customer, '1000.00');
        $payment = $this->payments->record($customer, ['amount' => '1000.00', 'payment_method' => 'cash'], [$invoice->id => '400.00']);

        $this->payments->allocate($payment, [$invoice->id => '600.00']);

        // One row, not two: the unique index expects exactly that.
        $this->assertSame(1, PaymentAllocation::where('payment_id', $payment->id)->count());
        $this->assertSame('1000.00', PaymentAllocation::first()->amount);
        $this->assertSame('0.00', $invoice->refresh()->balance_due);
    }

    public function test_allocations_are_suggested_oldest_first(): void
    {
        $customer = Customer::factory()->create();
        $older = $this->invoiceFor($customer, '600.00', ['invoice_date' => '2026-01-01', 'due_date' => '2026-01-15']);
        $newer = $this->invoiceFor($customer, '600.00', ['invoice_date' => '2026-06-01', 'due_date' => '2026-06-15']);

        $suggested = $this->payments->suggestAllocation($customer, '900.00');

        $this->assertSame(['600.00', '300.00'], array_values($suggested));
        $this->assertSame([$older->id, $newer->id], array_keys($suggested));
    }

    // -----------------------------------------------------------------
    // Guards
    // -----------------------------------------------------------------

    public function test_applying_more_than_was_received_is_refused(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoiceFor($customer, '1000.00');

        $this->expectException(DomainException::class);

        $this->payments->record(
            $customer,
            ['amount' => '500.00', 'payment_method' => 'cash'],
            [$invoice->id => '900.00'],
        );
    }

    public function test_applying_more_than_an_invoice_owes_is_refused(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoiceFor($customer, '300.00');

        $this->expectException(DomainException::class);

        $this->payments->record(
            $customer,
            ['amount' => '1000.00', 'payment_method' => 'cash'],
            [$invoice->id => '900.00'],
        );
    }

    public function test_a_payment_cannot_be_applied_to_another_customers_invoice(): void
    {
        $payer = Customer::factory()->create();
        $stranger = Customer::factory()->create();
        $theirInvoice = $this->invoiceFor($stranger, '500.00');

        $this->expectException(DomainException::class);

        $this->payments->record(
            $payer,
            ['amount' => '500.00', 'payment_method' => 'cash'],
            [$theirInvoice->id => '500.00'],
        );
    }

    public function test_a_cancelled_invoice_cannot_take_a_payment(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoiceFor($customer, '500.00');
        $this->invoices->cancel($invoice, 'Billed in error');

        $this->expectException(DomainException::class);

        $this->payments->record(
            $customer,
            ['amount' => '500.00', 'payment_method' => 'cash'],
            [$invoice->id => '500.00'],
        );
    }

    public function test_a_zero_payment_is_refused(): void
    {
        $this->expectException(DomainException::class);

        $this->payments->record(Customer::factory()->create(), ['amount' => '0.00', 'payment_method' => 'cash'], []);
    }

    public function test_a_failed_allocation_rolls_the_whole_payment_back(): void
    {
        $customer = Customer::factory()->create();
        $good = $this->invoiceFor($customer, '500.00');
        $tooMuch = $this->invoiceFor($customer, '100.00');

        try {
            $this->payments->record(
                $customer,
                ['amount' => '1000.00', 'payment_method' => 'cash'],
                [$good->id => '500.00', $tooMuch->id => '900.00'],
            );
        } catch (DomainException) {
            // Expected.
        }

        // Nothing may survive a partially applied payment.
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, PaymentAllocation::count());
        $this->assertSame('500.00', $good->refresh()->balance_due);
    }

    // -----------------------------------------------------------------
    // Reversal
    // -----------------------------------------------------------------

    public function test_reversing_a_payment_restores_the_invoice_balance(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoiceFor($customer, '1000.00');
        $payment = $this->payments->record($customer, ['amount' => '1000.00', 'payment_method' => 'cash'], [$invoice->id => '1000.00']);

        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);

        $this->payments->reverse($payment, 'Bounced cheque');

        $invoice->refresh();

        $this->assertSame(PaymentStatus::Reversed, $payment->refresh()->status);
        $this->assertSame('0.00', $invoice->amount_paid);
        $this->assertSame('1000.00', $invoice->balance_due);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
    }

    public function test_a_reversal_keeps_the_record_and_its_allocations(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoiceFor($customer, '500.00');
        $payment = $this->payments->record($customer, ['amount' => '500.00', 'payment_method' => 'cash'], [$invoice->id => '500.00']);

        $this->payments->reverse($payment, 'Entered in error');

        // Financial records are never deleted; the status is what stops them counting.
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'reversed']);
        $this->assertSame(1, PaymentAllocation::where('payment_id', $payment->id)->count());
        $this->assertSame('Entered in error', $payment->refresh()->reversal_reason);
        $this->assertNotNull($payment->reversed_at);
    }

    public function test_reversing_one_of_two_payments_leaves_the_other_counting(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoiceFor($customer, '1000.00');

        $first = $this->payments->record($customer, ['amount' => '600.00', 'payment_method' => 'cash'], [$invoice->id => '600.00']);
        $this->payments->record($customer, ['amount' => '400.00', 'payment_method' => 'cash'], [$invoice->id => '400.00']);

        $this->payments->reverse($first, 'Bounced');

        $invoice->refresh();

        $this->assertSame('400.00', $invoice->amount_paid);
        $this->assertSame('600.00', $invoice->balance_due);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
    }

    public function test_a_payment_cannot_be_reversed_twice(): void
    {
        $payment = $this->payments->record(Customer::factory()->create(), ['amount' => '100.00', 'payment_method' => 'cash'], []);
        $this->payments->reverse($payment, 'First');

        $this->expectException(DomainException::class);

        $this->payments->reverse($payment->refresh(), 'Second');
    }

    public function test_a_reversed_payment_no_longer_offers_credit(): void
    {
        $customer = Customer::factory()->create();
        $payment = $this->payments->record($customer, ['amount' => '900.00', 'payment_method' => 'cash'], []);

        $this->assertSame('900.00', $this->payments->availableCreditFor($customer));

        $this->payments->reverse($payment, 'Refunded');

        $this->assertSame('0.00', $this->payments->availableCreditFor($customer->refresh()));
    }

    // -----------------------------------------------------------------
    // HTTP layer
    // -----------------------------------------------------------------

    public function test_the_payment_pages_render(): void
    {
        $staff = $this->userWithRole(Role::BILLING_STAFF);
        $customer = Customer::factory()->create();
        $payment = Payment::factory()->for($customer)->create();

        $this->actingAs($staff)->get(route('payments.index'))->assertOk();
        $this->actingAs($staff)->get(route('payments.create'))->assertOk();
        $this->actingAs($staff)->get(route('payments.create', ['customer' => $customer->id]))->assertOk();
        $this->actingAs($staff)->get(route('payments.show', $payment))->assertOk()
            ->assertSee($payment->payment_reference);
    }

    public function test_a_payment_can_be_recorded_through_the_form(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoiceFor($customer, '1499.00');

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->post(route('payments.store'), [
            'customer_id' => $customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => '1499.00',
            'payment_method' => 'gcash',
            'reference_number' => 'GC-12345',
            'allocations' => [$invoice->id => '1499.00'],
        ])->assertRedirect();

        $payment = Payment::first();

        $this->assertMatchesRegularExpression('/^PAY-\d{4}-\d{6}$/', $payment->payment_reference);
        $this->assertSame('GC-12345', $payment->reference_number);
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
    }

    public function test_the_form_refuses_applying_more_than_was_received(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoiceFor($customer, '2000.00');

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->post(route('payments.store'), [
            'customer_id' => $customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => '500.00',
            'payment_method' => 'cash',
            'allocations' => [$invoice->id => '900.00'],
        ])->assertSessionHasErrors('allocations');

        $this->assertSame(0, Payment::count());
    }

    public function test_a_future_dated_payment_is_refused(): void
    {
        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->post(route('payments.store'), [
            'customer_id' => Customer::factory()->create()->id,
            'payment_date' => now()->addWeek()->toDateString(),
            'amount' => '500.00',
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('payment_date');
    }

    public function test_a_payment_can_be_reversed_through_the_form(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoiceFor($customer, '700.00');
        $payment = $this->payments->record($customer, ['amount' => '700.00', 'payment_method' => 'cash'], [$invoice->id => '700.00']);

        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->patch(route('payments.reverse', $payment), ['reason' => 'Bounced cheque'])
            ->assertRedirect();

        $this->assertSame(PaymentStatus::Reversed, $payment->refresh()->status);
        $this->assertSame('700.00', $invoice->refresh()->balance_due);
    }

    public function test_reversing_requires_a_reason(): void
    {
        $payment = Payment::factory()->create();

        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->patch(route('payments.reverse', $payment), ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_billing_staff_can_record_but_not_reverse(): void
    {
        $staff = $this->userWithRole(Role::BILLING_STAFF);
        $payment = Payment::factory()->create();

        $this->actingAs($staff)->get(route('payments.create'))->assertOk();
        $this->actingAs($staff)
            ->patch(route('payments.reverse', $payment), ['reason' => 'Nope'])
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Completed, $payment->refresh()->status);
    }

    public function test_an_accountant_can_record_and_reverse(): void
    {
        $accountant = $this->userWithRole(Role::ACCOUNTANT);

        $this->actingAs($accountant)->get(route('payments.index'))->assertOk();
        $this->actingAs($accountant)->get(route('payments.create'))->assertOk();
    }

    public function test_a_technician_cannot_reach_payments(): void
    {
        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('payments.index'))
            ->assertForbidden();
    }
}
