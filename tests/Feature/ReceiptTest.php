<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\ReceiptService;
use App\Services\SettingsService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    private ReceiptService $receipts;

    private PaymentService $payments;

    private InvoiceService $invoices;

    private SettingsService $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleAndPermissionSeeder::class, SystemSettingSeeder::class]);

        $this->settings = app(SettingsService::class);
        $this->settings->flush();

        $this->receipts = app(ReceiptService::class);
        $this->payments = app(PaymentService::class);
        $this->invoices = app(InvoiceService::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->value('id'));

        return $user->fresh();
    }

    /** A customer with one invoice, settled by one payment. */
    private function settledPayment(string $amount = '1000.00', ?string $paid = null): Payment
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoices->create($customer, [
            ['description' => 'Monthly service', 'unit_price' => $amount],
        ]);

        return $this->payments->record(
            $customer,
            ['amount' => $paid ?? $amount, 'payment_method' => 'cash'],
            [$invoice->id => $paid ?? $amount],
        );
    }

    // -----------------------------------------------------------------
    // Issuing
    // -----------------------------------------------------------------

    public function test_a_receipt_can_be_issued_for_a_completed_payment(): void
    {
        $payment = $this->settledPayment();
        $actor = $this->userWithRole(Role::BILLING_STAFF);

        $receipt = $this->receipts->issue($payment, $actor);

        $this->assertMatchesRegularExpression('/^OR-\d{4}-\d{6}$/', $receipt->receipt_number);
        $this->assertSame($payment->id, $receipt->payment_id);
        $this->assertSame($actor->id, $receipt->issued_by);
        $this->assertNotNull($receipt->issued_at);
    }

    public function test_the_receipt_number_uses_the_configured_prefix(): void
    {
        $this->settings->set('billing.receipt_prefix', 'RCPT');

        $receipt = app(ReceiptService::class)->issue($this->settledPayment());

        $this->assertMatchesRegularExpression('/^RCPT-\d{4}-\d{6}$/', $receipt->receipt_number);
    }

    public function test_receipt_numbers_do_not_repeat(): void
    {
        $numbers = collect(range(1, 5))
            ->map(fn () => $this->receipts->issue($this->settledPayment())->receipt_number);

        $this->assertCount(5, $numbers->unique());
    }

    public function test_issuing_twice_returns_the_same_receipt(): void
    {
        $payment = $this->settledPayment();

        $first = $this->receipts->issue($payment);
        $second = $this->receipts->issue($payment->refresh());

        // One receipt per payment; pressing the button again is not an error.
        $this->assertTrue($first->is($second));
        $this->assertSame(1, Receipt::count());
    }

    public function test_a_reversed_payment_cannot_be_receipted(): void
    {
        $payment = $this->settledPayment();
        $this->payments->reverse($payment, 'Bounced cheque');

        $this->expectException(DomainException::class);

        $this->receipts->issue($payment->refresh());
    }

    // -----------------------------------------------------------------
    // Contents
    // -----------------------------------------------------------------

    public function test_the_receipt_shows_everything_the_specification_requires(): void
    {
        // The factory adds a middle name most of the time, so assert against
        // the rendered full name rather than a hard-coded "First Last".
        $customer = Customer::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $invoice = $this->invoices->create($customer, [
            ['description' => 'Monthly service', 'unit_price' => '1500.00'],
        ]);
        $cashier = $this->userWithRole(Role::BILLING_STAFF);

        $payment = $this->payments->record(
            $customer,
            ['amount' => '1000.00', 'payment_method' => 'gcash', 'reference_number' => 'GC-99'],
            [$invoice->id => '1000.00'],
            $cashier,
        );

        $receipt = $this->receipts->issue($payment, $cashier);

        $this->actingAs($cashier)->get(route('receipts.print', $receipt))
            ->assertOk()
            ->assertSee('OFFICIAL RECEIPT')
            ->assertSee('ISP Billing')                    // ISP name
            ->assertSee($customer->full_name)             // customer name
            ->assertSee($customer->account_number)        // account number
            ->assertSee($receipt->receipt_number)         // receipt number
            ->assertSee($payment->payment_reference)      // payment reference
            ->assertSee($payment->payment_date->format('d M Y'))
            ->assertSee($invoice->invoice_number)         // invoice number
            ->assertSee('1,000.00')                       // amount paid
            ->assertSee('GCash')                          // payment method
            ->assertSee('Remaining balance')
            ->assertSee('500.00')                         // the balance left
            ->assertSee('Received by')
            ->assertSee($cashier->full_name);
    }

    public function test_the_receipt_reports_credit_held_when_the_payment_overshoots(): void
    {
        $customer = Customer::factory()->create();
        $invoice = $this->invoices->create($customer, [
            ['description' => 'Monthly service', 'unit_price' => '400.00'],
        ]);
        $payment = $this->payments->record(
            $customer,
            ['amount' => '1000.00', 'payment_method' => 'cash'],
            [$invoice->id => '400.00'],
        );

        $receipt = $this->receipts->issue($payment);

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->get(route('receipts.show', $receipt))
            ->assertOk()
            ->assertSee('Held as credit')
            ->assertSee('600.00');
    }

    public function test_a_receipt_for_a_later_reversed_payment_is_marked_void(): void
    {
        $payment = $this->settledPayment();
        $receipt = $this->receipts->issue($payment);

        $this->payments->reverse($payment->refresh(), 'Bounced cheque');

        // The receipt is kept — it was issued — but must not read as valid.
        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->get(route('receipts.show', $receipt))
            ->assertOk()
            ->assertSee('VOID')
            ->assertSee('no longer valid');

        $this->assertDatabaseHas('receipts', ['id' => $receipt->id]);
    }

    // -----------------------------------------------------------------
    // HTTP layer
    // -----------------------------------------------------------------

    public function test_a_receipt_is_issued_from_the_payment_page(): void
    {
        $payment = $this->settledPayment();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('payments.receipt', $payment))
            ->assertRedirect();

        $this->assertSame(1, Receipt::count());
        $this->assertNotNull($payment->refresh()->receipt);
    }

    public function test_the_payment_page_offers_the_receipt_once_issued(): void
    {
        $payment = $this->settledPayment();
        $staff = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($staff)->get(route('payments.show', $payment))
            ->assertOk()->assertSee('Issue receipt');

        $receipt = $this->receipts->issue($payment, $staff);

        $this->actingAs($staff)->get(route('payments.show', $payment->refresh()))
            ->assertOk()
            ->assertSee($receipt->receipt_number)
            ->assertDontSee('Issue receipt');
    }

    public function test_the_receipt_list_renders_and_can_be_searched(): void
    {
        $target = $this->receipts->issue($this->settledPayment());
        $this->receipts->issue($this->settledPayment());

        $staff = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($staff)->get(route('receipts.index'))
            ->assertOk()->assertViewHas('receipts', fn ($r) => $r->total() === 2);

        $this->actingAs($staff)->get(route('receipts.index', ['search' => $target->receipt_number]))
            ->assertOk()->assertViewHas('receipts', fn ($r) => $r->total() === 1);
    }

    public function test_issuing_a_receipt_for_a_reversed_payment_is_refused_over_http(): void
    {
        $payment = $this->settledPayment();
        $this->payments->reverse($payment, 'Bounced cheque');

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('payments.receipt', $payment->refresh()))
            ->assertForbidden();

        $this->assertSame(0, Receipt::count());
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_an_accountant_may_read_receipts_but_not_issue_them(): void
    {
        $payment = $this->settledPayment();
        $accountant = $this->userWithRole(Role::ACCOUNTANT);

        $this->actingAs($accountant)->get(route('receipts.index'))->assertOk();
        $this->actingAs($accountant)->post(route('payments.receipt', $payment))->assertForbidden();

        $receipt = $this->receipts->issue($payment);

        $this->actingAs($accountant)->get(route('receipts.show', $receipt))->assertOk();
        $this->actingAs($accountant)->get(route('receipts.print', $receipt))->assertOk();
    }

    public function test_a_technician_cannot_reach_receipts(): void
    {
        $receipt = $this->receipts->issue($this->settledPayment());
        $technician = $this->userWithRole(Role::TECHNICIAN);

        $this->actingAs($technician)->get(route('receipts.index'))->assertForbidden();
        $this->actingAs($technician)->get(route('receipts.show', $receipt))->assertForbidden();
    }
}
