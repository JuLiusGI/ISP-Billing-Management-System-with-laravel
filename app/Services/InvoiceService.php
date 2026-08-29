<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Invoice construction and arithmetic.
 *
 * All money is handled with bcmath on strings. Casting to float anywhere in
 * here would reintroduce the rounding error the DECIMAL columns exist to
 * prevent.
 *
 * Totals are derived as:
 *
 *   subtotal       = Σ (item.quantity × item.unit_price)
 *   discount_total = Σ item.discount_amount   + invoice-level discount
 *   charges_total  = invoice-level additional charges
 *   taxable base   = subtotal − discount_total + charges_total
 *   tax_total      = taxable base × rate, when tax is enabled
 *   total_amount   = taxable base + tax_total
 *   balance_due    = total_amount − allocated payments, floored at zero
 *
 * Each item's own line_total is (quantity × unit_price) − its discount, so the
 * item lines and the invoice header always agree.
 */
class InvoiceService
{
    private const NUMBER_ATTEMPTS = 5;

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Creates an invoice with its line items in one transaction.
     *
     * @param  array<int, array{description: string, item_type?: string, quantity?: numeric-string|int|float, unit_price: numeric-string|int|float, discount_amount?: numeric-string|int|float}>  $items
     * @param  array<string, mixed>  $attributes
     */
    public function create(Customer $customer, array $items, array $attributes = [], ?User $actor = null): Invoice
    {
        if ($items === []) {
            throw new DomainException('An invoice needs at least one line item.');
        }

        $invoiceDate = Carbon::parse($attributes['invoice_date'] ?? now())->startOfDay();
        $dueDate = isset($attributes['due_date'])
            ? Carbon::parse($attributes['due_date'])->startOfDay()
            : $this->dueDateFor($invoiceDate);

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($customer, $items, $attributes, $invoiceDate, $dueDate, $actor): Invoice {
                    $invoice = Invoice::create([
                        'invoice_number' => $this->nextInvoiceNumber(),
                        'customer_id' => $customer->id,
                        'subscription_id' => $attributes['subscription_id'] ?? null,
                        'billing_cycle_id' => $attributes['billing_cycle_id'] ?? null,
                        'billing_period_start' => $attributes['billing_period_start'] ?? null,
                        'billing_period_end' => $attributes['billing_period_end'] ?? null,
                        'invoice_date' => $invoiceDate->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'status' => $attributes['status'] ?? InvoiceStatus::Unpaid,
                        'notes' => $attributes['notes'] ?? null,
                        'created_by' => $actor?->id,
                    ]);

                    foreach ($items as $item) {
                        $this->addItem($invoice, $item);
                    }

                    return $this->recalculate(
                        $invoice,
                        (string) ($attributes['discount_total'] ?? '0'),
                        (string) ($attributes['charges_total'] ?? '0'),
                    );
                });
            } catch (UniqueConstraintViolationException $e) {
                // invoice_number races are retried; a duplicate billing period
                // is a real conflict and must surface to the caller.
                if ($attempt >= self::NUMBER_ATTEMPTS || ! str_contains($e->getMessage(), 'invoice_number')) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Recomputes every stored total from the invoice's items and payments.
     *
     * Safe to run repeatedly; it is the single place invoice arithmetic lives.
     */
    public function recalculate(Invoice $invoice, ?string $extraDiscount = null, ?string $extraCharges = null): Invoice
    {
        $invoice->loadMissing('items');

        $extraDiscount ??= $this->invoiceLevelDiscount($invoice);
        $extraCharges ??= (string) $invoice->charges_total;

        $subtotal = '0.00';
        $itemDiscounts = '0.00';

        foreach ($invoice->items as $item) {
            $gross = bcmul((string) $item->quantity, (string) $item->unit_price, 2);
            $subtotal = bcadd($subtotal, $gross, 2);
            $itemDiscounts = bcadd($itemDiscounts, (string) $item->discount_amount, 2);
        }

        $discountTotal = bcadd($itemDiscounts, $this->normalise($extraDiscount), 2);
        $chargesTotal = $this->normalise($extraCharges);

        $taxableBase = bcadd(bcsub($subtotal, $discountTotal, 2), $chargesTotal, 2);

        // A fully discounted invoice must not produce negative tax.
        if (bccomp($taxableBase, '0', 2) === -1) {
            $taxableBase = '0.00';
        }

        $taxTotal = $this->settings->taxEnabled()
            ? bcdiv(bcmul($taxableBase, $this->settings->taxRate(), 4), '100', 2)
            : '0.00';

        $totalAmount = bcadd($taxableBase, $taxTotal, 2);
        $amountPaid = $this->allocatedTotal($invoice);
        $balanceDue = bcsub($totalAmount, $amountPaid, 2);

        if (bccomp($balanceDue, '0', 2) === -1) {
            $balanceDue = '0.00';
        }

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'charges_total' => $chargesTotal,
            'tax_total' => $taxTotal,
            'total_amount' => $totalAmount,
            'amount_paid' => $amountPaid,
            'balance_due' => $balanceDue,
            'status' => $this->deriveStatus($invoice, $totalAmount, $amountPaid),
        ])->save();

        return $invoice;
    }

    /**
     * Cancels an invoice. Financial records are never deleted, so this leaves
     * the row in place with a reason and no balance.
     */
    public function cancel(Invoice $invoice, string $reason, ?User $actor = null): Invoice
    {
        if ($invoice->allocations()->exists()) {
            throw new DomainException(
                'This invoice has payments applied to it. Reverse those payments before cancelling.'
            );
        }

        if (in_array($invoice->status, [InvoiceStatus::Cancelled, InvoiceStatus::Void], true)) {
            throw new DomainException('This invoice is already cancelled.');
        }

        $invoice->forceFill([
            'status' => InvoiceStatus::Cancelled,
            'balance_due' => '0.00',
            'cancelled_at' => now(),
            'cancelled_by' => $actor?->id,
            'cancellation_reason' => $reason,
        ])->save();

        return $invoice;
    }

    /** Issue date plus the configured grace period. */
    public function dueDateFor(Carbon $invoiceDate): Carbon
    {
        return $invoiceDate->copy()->addDays($this->settings->gracePeriodDays());
    }

    public function nextInvoiceNumber(): string
    {
        $sequence = (Invoice::withTrashed()->max('id') ?? 0) + 1;

        return sprintf('%s-%s-%06d', $this->settings->invoicePrefix(), date('Y'), $sequence);
    }

    /** @param array<string, mixed> $item */
    private function addItem(Invoice $invoice, array $item): void
    {
        $quantity = $this->normalise($item['quantity'] ?? 1);
        $unitPrice = $this->normalise($item['unit_price']);
        $discount = $this->normalise($item['discount_amount'] ?? 0);

        $invoice->items()->create([
            'description' => $item['description'],
            'item_type' => $item['item_type'] ?? 'other',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discount,
            'line_total' => bcsub(bcmul($quantity, $unitPrice, 2), $discount, 2),
        ]);
    }

    /** Only completed payments count toward what has been paid. */
    private function allocatedTotal(Invoice $invoice): string
    {
        $total = $invoice->allocations()
            ->whereHas('payment', fn ($q) => $q->where('status', PaymentStatus::Completed))
            ->sum('amount');

        return $this->normalise($total);
    }

    /**
     * The invoice-level discount, separated from the items' own discounts so
     * recalculating does not double-count them.
     */
    private function invoiceLevelDiscount(Invoice $invoice): string
    {
        $itemDiscounts = '0.00';

        foreach ($invoice->items as $item) {
            $itemDiscounts = bcadd($itemDiscounts, (string) $item->discount_amount, 2);
        }

        $remainder = bcsub((string) $invoice->discount_total, $itemDiscounts, 2);

        return bccomp($remainder, '0', 2) === -1 ? '0.00' : $remainder;
    }

    private function deriveStatus(Invoice $invoice, string $total, string $paid): InvoiceStatus
    {
        // Cancelled, void and draft invoices are not driven by their balance.
        if (in_array($invoice->status, [InvoiceStatus::Cancelled, InvoiceStatus::Void, InvoiceStatus::Draft], true)) {
            return $invoice->status;
        }

        if (bccomp($paid, '0', 2) === 0) {
            return $invoice->isOverdue() ? InvoiceStatus::Overdue : InvoiceStatus::Unpaid;
        }

        if (bccomp($paid, $total, 2) >= 0) {
            return InvoiceStatus::Paid;
        }

        return InvoiceStatus::PartiallyPaid;
    }

    /**
     * Brings a value to a two-decimal string without a float round trip when
     * it is already exact. Only genuine floats are rounded through
     * number_format; strings and integers go straight to bcmath.
     */
    private function normalise(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        if (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }

        return bcadd((string) $value, '0', 2);
    }
}
