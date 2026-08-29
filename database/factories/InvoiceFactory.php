<?php

namespace Database\Factories;

use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $subtotal = fake()->randomElement([999, 1299, 1499, 1999, 2499]);
        $periodStart = fake()->dateTimeBetween('-1 year', 'now')->modify('first day of this month');
        $invoiceDate = (clone $periodStart);

        return [
            'invoice_number' => 'INV-'.fake()->unique()->numerify('########'),
            'customer_id' => Customer::factory(),
            'subscription_id' => null,
            'billing_cycle_id' => null,
            'billing_period_start' => $periodStart,
            'billing_period_end' => (clone $periodStart)->modify('last day of this month'),
            'invoice_date' => $invoiceDate,
            'due_date' => (clone $invoiceDate)->modify('+15 days'),
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'charges_total' => 0,
            'tax_total' => 0,
            'total_amount' => $subtotal,
            'amount_paid' => 0,
            'balance_due' => $subtotal,
            'status' => InvoiceStatus::Unpaid,
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        // Every invoice gets a line item that adds up to its subtotal, so the
        // stored totals and the items always agree.
        return $this->afterCreating(function (Invoice $invoice): void {
            if ($invoice->items()->exists()) {
                return;
            }

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Monthly internet service',
                'item_type' => InvoiceItemType::Subscription,
                'quantity' => 1,
                'unit_price' => $invoice->subtotal,
                'discount_amount' => 0,
                'line_total' => $invoice->subtotal,
            ]);
        });
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => InvoiceStatus::Draft]);
    }

    public function overdue(int $daysPastDue = 30): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Overdue,
            'due_date' => now()->subDays($daysPastDue),
            'invoice_date' => now()->subDays($daysPastDue + 15),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Cancelled,
            'balance_due' => 0,
        ]);
    }

    /**
     * Settles the invoice with a real payment and allocation rather than just
     * writing the totals, so balance checks against allocations hold up.
     */
    public function paid(): static
    {
        return $this->afterCreating(function (Invoice $invoice): void {
            $this->settle($invoice, (string) $invoice->total_amount, InvoiceStatus::Paid);
        });
    }

    /** Applies a part payment, leaving a genuine outstanding balance. */
    public function partiallyPaid(?float $amount = null): static
    {
        return $this->afterCreating(function (Invoice $invoice) use ($amount): void {
            $paid = $amount !== null
                ? number_format($amount, 2, '.', '')
                : bcdiv((string) $invoice->total_amount, '2', 2);

            $this->settle($invoice, $paid, InvoiceStatus::PartiallyPaid);
        });
    }

    private function settle(Invoice $invoice, string $amount, InvoiceStatus $status): void
    {
        $payment = Payment::create([
            'payment_reference' => 'PAY-'.fake()->unique()->numerify('########'),
            'customer_id' => $invoice->customer_id,
            'payment_date' => $invoice->invoice_date,
            'amount' => $amount,
            'allocated_amount' => $amount,
            'payment_method' => 'cash',
            'status' => PaymentStatus::Completed,
        ]);

        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
        ]);

        $invoice->forceFill([
            'amount_paid' => $amount,
            'balance_due' => bcsub((string) $invoice->total_amount, $amount, 2),
            'status' => $status,
        ])->save();
    }
}
