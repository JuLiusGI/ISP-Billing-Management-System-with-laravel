<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Role;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleAndPermissionSeeder::class, SystemSettingSeeder::class]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->value('id'));

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function validPayload(Customer $customer, array $overrides = []): array
    {
        return array_replace([
            'customer_id' => $customer->id,
            'subscription_id' => null,
            'invoice_date' => '2026-08-01',
            'due_date' => '2026-08-15',
            'billing_period_start' => '2026-08-01',
            'billing_period_end' => '2026-08-31',
            'discount_total' => '0.00',
            'charges_total' => '0.00',
            'notes' => 'Issued manually.',
            'items' => [
                [
                    'description' => 'Monthly internet service',
                    'item_type' => 'subscription',
                    'quantity' => '1.00',
                    'unit_price' => '1499.00',
                    'discount_amount' => '0.00',
                ],
            ],
        ], $overrides);
    }

    /** Applies a real payment so the invoice becomes a settled record. */
    private function applyPayment(Invoice $invoice, string $amount): void
    {
        $payment = Payment::factory()->for($invoice->customer)->create([
            'amount' => $amount, 'allocated_amount' => $amount,
        ]);

        PaymentAllocation::create([
            'payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => $amount,
        ]);

        app(InvoiceService::class)->recalculate($invoice->refresh());
    }

    // -----------------------------------------------------------------
    // Listing, filtering and viewing
    // -----------------------------------------------------------------

    public function test_the_invoice_pages_render(): void
    {
        $staff = $this->userWithRole(Role::BILLING_STAFF);
        $invoice = Invoice::factory()->create();

        $this->actingAs($staff)->get(route('invoices.index'))->assertOk()->assertSee('Invoices');
        $this->actingAs($staff)->get(route('invoices.create'))->assertOk()->assertSee('New invoice');
        $this->actingAs($staff)->get(route('invoices.show', $invoice))->assertOk()
            ->assertSee($invoice->invoice_number);
        $this->actingAs($staff)->get(route('invoices.edit', $invoice))->assertOk();
    }

    public function test_the_printable_invoice_renders_with_the_company_details(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->get(route('invoices.print', $invoice))
            ->assertOk()
            ->assertSee('INVOICE')
            ->assertSee('ISP Billing')
            ->assertSee($invoice->customer->account_number)
            ->assertSee('Balance due');
    }

    public function test_invoices_can_be_searched_by_number_and_customer(): void
    {
        $target = Invoice::factory()->create();
        Invoice::factory()->count(3)->create();

        $staff = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($staff)->get(route('invoices.index', ['search' => $target->invoice_number]))
            ->assertViewHas('invoices', fn ($i) => $i->total() === 1);

        $this->actingAs($staff)
            ->get(route('invoices.index', ['search' => $target->customer->account_number]))
            ->assertViewHas('invoices', fn ($i) => $i->total() === 1);
    }

    public function test_invoices_can_be_filtered_by_status_and_view(): void
    {
        Invoice::factory()->count(2)->overdue()->create();
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'balance_due' => 0]);
        Invoice::factory()->create(['status' => InvoiceStatus::Unpaid, 'due_date' => now()->addWeek()]);

        $staff = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($staff)->get(route('invoices.index', ['status' => 'paid']))
            ->assertViewHas('invoices', fn ($i) => $i->total() === 1);

        $this->actingAs($staff)->get(route('invoices.index', ['view' => 'overdue']))
            ->assertViewHas('invoices', fn ($i) => $i->total() === 2);

        // Outstanding covers unpaid, partially paid and overdue.
        $this->actingAs($staff)->get(route('invoices.index', ['view' => 'outstanding']))
            ->assertViewHas('invoices', fn ($i) => $i->total() === 3);
    }

    public function test_invoices_can_be_filtered_by_date_and_amount(): void
    {
        Invoice::factory()->create(['invoice_date' => '2026-01-15', 'total_amount' => 500]);
        Invoice::factory()->create(['invoice_date' => '2026-08-15', 'total_amount' => 5000]);

        $staff = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($staff)->get(route('invoices.index', ['from' => '2026-08-01']))
            ->assertViewHas('invoices', fn ($i) => $i->total() === 1);

        $this->actingAs($staff)->get(route('invoices.index', ['min' => 1000]))
            ->assertViewHas('invoices', fn ($i) => $i->total() === 1);
    }

    public function test_the_listed_totals_cover_the_whole_filtered_set_not_just_the_page(): void
    {
        // 25 invoices at 100 each spills onto a second page of 20.
        Invoice::factory()->count(25)->create([
            'total_amount' => 100, 'balance_due' => 100, 'status' => InvoiceStatus::Unpaid,
        ]);

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->get(route('invoices.index'))
            ->assertViewHas('invoicedTotal', fn ($total) => (float) $total === 2500.0);
    }

    // -----------------------------------------------------------------
    // Manual creation
    // -----------------------------------------------------------------

    public function test_an_invoice_can_be_created_by_hand(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('invoices.store'), $this->validPayload($customer))
            ->assertRedirect();

        $invoice = Invoice::first();

        $this->assertSame($customer->id, $invoice->customer_id);
        $this->assertSame('1499.00', $invoice->total_amount);
        $this->assertCount(1, $invoice->items);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{6}$/', $invoice->invoice_number);
    }

    public function test_an_invoice_can_carry_several_lines_with_discounts_and_charges(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->post(
            route('invoices.store'),
            $this->validPayload($customer, [
                'discount_total' => '100.00',
                'charges_total' => '50.00',
                'items' => [
                    ['description' => 'Service', 'item_type' => 'subscription', 'quantity' => '1.00', 'unit_price' => '1000.00', 'discount_amount' => '0.00'],
                    ['description' => 'Router', 'item_type' => 'other', 'quantity' => '2.00', 'unit_price' => '500.00', 'discount_amount' => '200.00'],
                ],
            ])
        )->assertRedirect();

        $invoice = Invoice::first();

        $this->assertSame('2000.00', $invoice->subtotal);
        $this->assertSame('300.00', $invoice->discount_total);
        $this->assertSame('50.00', $invoice->charges_total);
        $this->assertSame('1750.00', $invoice->total_amount);
    }

    public function test_an_invoice_needs_at_least_one_line(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('invoices.store'), $this->validPayload($customer, ['items' => []]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Invoice::count());
    }

    public function test_a_line_discount_cannot_exceed_the_line_total(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->post(
            route('invoices.store'),
            $this->validPayload($customer, ['items' => [
                ['description' => 'Service', 'item_type' => 'other', 'quantity' => '1.00', 'unit_price' => '100.00', 'discount_amount' => '150.00'],
            ]])
        )->assertSessionHasErrors('items.0.discount_amount');
    }

    public function test_the_invoice_discount_cannot_exceed_the_line_items(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('invoices.store'), $this->validPayload($customer, ['discount_total' => '9999.00']))
            ->assertSessionHasErrors('discount_total');
    }

    public function test_a_due_date_before_the_invoice_date_is_rejected(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('invoices.store'), $this->validPayload($customer, ['due_date' => '2026-07-01']))
            ->assertSessionHasErrors('due_date');
    }

    // -----------------------------------------------------------------
    // Editing and immutability
    // -----------------------------------------------------------------

    public function test_an_unpaid_invoice_can_be_amended(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Unpaid]);

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->put(
            route('invoices.update', $invoice),
            $this->validPayload($invoice->customer, ['items' => [
                ['description' => 'Revised service', 'item_type' => 'subscription', 'quantity' => '1.00', 'unit_price' => '2000.00', 'discount_amount' => '0.00'],
            ]])
        )->assertRedirect();

        $invoice->refresh();

        $this->assertSame('2000.00', $invoice->total_amount);
        $this->assertCount(1, $invoice->items);
        $this->assertSame('Revised service', $invoice->items->first()->description);
    }

    public function test_an_invoice_with_a_payment_applied_cannot_be_edited(): void
    {
        $invoice = Invoice::factory()->create([
            'total_amount' => 1000, 'balance_due' => 1000, 'status' => InvoiceStatus::Unpaid,
        ]);
        $this->applyPayment($invoice, '400.00');

        $staff = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($staff)->get(route('invoices.edit', $invoice))->assertForbidden();
        $this->actingAs($staff)
            ->put(route('invoices.update', $invoice), $this->validPayload($invoice->customer))
            ->assertForbidden();
    }

    public function test_a_cancelled_invoice_cannot_be_edited(): void
    {
        $invoice = Invoice::factory()->cancelled()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->get(route('invoices.edit', $invoice))
            ->assertForbidden();
    }

    public function test_editing_cannot_move_an_invoice_to_another_customer(): void
    {
        $invoice = Invoice::factory()->create();
        $other = Customer::factory()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->put(route('invoices.update', $invoice), $this->validPayload($other));

        $this->assertNotSame($other->id, $invoice->refresh()->customer_id);
    }

    // -----------------------------------------------------------------
    // Cancellation
    // -----------------------------------------------------------------

    public function test_an_invoice_can_be_cancelled_with_a_reason(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Unpaid]);

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->patch(route('invoices.cancel', $invoice), ['reason' => 'Billed in error'])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::Cancelled, $invoice->status);
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertSame('Billed in error', $invoice->cancellation_reason);
        // Kept, never deleted.
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_cancelling_requires_a_reason(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Unpaid]);

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->patch(route('invoices.cancel', $invoice), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(InvoiceStatus::Unpaid, $invoice->refresh()->status);
    }

    public function test_an_invoice_with_a_payment_cannot_be_cancelled(): void
    {
        $invoice = Invoice::factory()->create([
            'total_amount' => 1000, 'balance_due' => 1000, 'status' => InvoiceStatus::Unpaid,
        ]);
        $this->applyPayment($invoice, '1000.00');

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->patch(route('invoices.cancel', $invoice), ['reason' => 'Changed my mind'])
            ->assertForbidden();

        $this->assertNotSame(InvoiceStatus::Cancelled, $invoice->refresh()->status);
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_an_accountant_may_read_invoices_but_not_change_them(): void
    {
        $accountant = $this->userWithRole(Role::ACCOUNTANT);
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Unpaid]);

        $this->actingAs($accountant)->get(route('invoices.index'))->assertOk();
        $this->actingAs($accountant)->get(route('invoices.show', $invoice))->assertOk();
        $this->actingAs($accountant)->get(route('invoices.print', $invoice))->assertOk();
        $this->actingAs($accountant)->get(route('invoices.create'))->assertForbidden();
        $this->actingAs($accountant)->get(route('invoices.edit', $invoice))->assertForbidden();
        $this->actingAs($accountant)
            ->patch(route('invoices.cancel', $invoice), ['reason' => 'No'])
            ->assertForbidden();
    }

    public function test_a_technician_cannot_reach_invoices(): void
    {
        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('invoices.index'))
            ->assertForbidden();
    }
}
