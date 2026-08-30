<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Notifications\PaymentReceived;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recording payments and applying them to invoices.
 *
 * A payment is money received; an allocation is that money being applied to a
 * particular invoice. Keeping the two apart is what lets one payment settle
 * several invoices, several payments settle one invoice, and an overpayment
 * sit as unapplied credit rather than being forced somewhere it does not
 * belong.
 *
 * Every write here runs in a transaction, and invoices are locked while their
 * balance is being read and changed, so two cashiers taking money for the same
 * invoice at once cannot both allocate against the same balance.
 */
class PaymentService
{
    private const REFERENCE_ATTEMPTS = 5;

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly CustomerNotifier $notifier,
    ) {}

    /**
     * Records a payment and applies it to the given invoices.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, numeric-string|float|int>  $allocations  invoice id => amount
     */
    public function record(Customer $customer, array $attributes, array $allocations, ?User $actor = null): Payment
    {
        $amount = $this->normalise($attributes['amount'] ?? 0);

        if (bccomp($amount, '0', 2) !== 1) {
            throw new DomainException('A payment must be for more than zero.');
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                $payment = DB::transaction(function () use ($customer, $attributes, $allocations, $amount, $actor): Payment {
                    $payment = Payment::create([
                        'payment_reference' => $this->nextReference(),
                        'customer_id' => $customer->id,
                        'payment_date' => $attributes['payment_date'] ?? now()->toDateString(),
                        'amount' => $amount,
                        'allocated_amount' => '0.00',
                        'payment_method' => $attributes['payment_method'],
                        'reference_number' => $attributes['reference_number'] ?? null,
                        'received_by' => $actor?->id,
                        'notes' => $attributes['notes'] ?? null,
                        'status' => PaymentStatus::Completed,
                    ]);

                    $this->applyAllocations($payment, $allocations);

                    return $payment->refresh();
                });

                // After commit: the money is recorded whether or not the
                // acknowledgement reaches the customer.
                $this->notifier->send(
                    $customer->refresh(),
                    'payment_received',
                    new PaymentReceived($payment),
                );

                return $payment;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::REFERENCE_ATTEMPTS || ! str_contains($e->getMessage(), 'payment_reference')) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Applies part of an existing payment's unallocated credit to invoices.
     *
     * @param  array<int, numeric-string|float|int>  $allocations  invoice id => amount
     */
    public function allocate(Payment $payment, array $allocations): Payment
    {
        if ($payment->status !== PaymentStatus::Completed) {
            throw new DomainException('Only a completed payment can be allocated.');
        }

        return DB::transaction(function () use ($payment, $allocations): Payment {
            $this->applyAllocations($payment, $allocations);

            return $payment->refresh();
        });
    }

    /**
     * Reverses a payment: a bounced cheque, a mistaken entry, a refund.
     *
     * The row and its allocations stay exactly where they are — deleting a
     * financial record would erase the trail. The status is what stops the
     * money counting, so recalculating the invoices it touched restores their
     * balances.
     */
    public function reverse(Payment $payment, string $reason, ?User $actor = null): Payment
    {
        if ($payment->status !== PaymentStatus::Completed) {
            throw new DomainException('This payment has already been reversed or cancelled.');
        }

        return DB::transaction(function () use ($payment, $reason, $actor): Payment {
            $affected = $payment->allocations()->pluck('invoice_id');

            $payment->forceFill([
                'status' => PaymentStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => $actor?->id,
                'reversal_reason' => $reason,
            ])->save();

            Invoice::whereIn('id', $affected)->lockForUpdate()->get()
                ->each(fn (Invoice $invoice) => $this->invoices->recalculate($invoice));

            return $payment->refresh();
        });
    }

    /**
     * Invoices this customer can still have money applied to, oldest first.
     *
     * @return Collection<int, Invoice>
     */
    public function outstandingInvoicesFor(Customer $customer): Collection
    {
        return $customer->invoices()
            ->outstanding()
            ->where('balance_due', '>', 0)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Spreads an amount across a customer's outstanding invoices, oldest
     * first, which is what a cashier taking a lump sum would do by hand.
     *
     * @return array<int, string> invoice id => amount
     */
    public function suggestAllocation(Customer $customer, string $amount): array
    {
        $remaining = $this->normalise($amount);
        $suggested = [];

        foreach ($this->outstandingInvoicesFor($customer) as $invoice) {
            if (bccomp($remaining, '0', 2) !== 1) {
                break;
            }

            $apply = bccomp($remaining, (string) $invoice->balance_due, 2) === -1
                ? $remaining
                : (string) $invoice->balance_due;

            $suggested[$invoice->id] = $apply;
            $remaining = bcsub($remaining, $apply, 2);
        }

        return $suggested;
    }

    /** Money received but not yet applied to any invoice, across all payments. */
    public function availableCreditFor(Customer $customer): string
    {
        $credit = '0.00';

        foreach ($customer->payments()->completed()->get() as $payment) {
            $credit = bcadd($credit, $payment->unallocatedAmount(), 2);
        }

        return $credit;
    }

    public function nextReference(): string
    {
        $sequence = (Payment::withTrashed()->max('id') ?? 0) + 1;

        return sprintf('PAY-%s-%06d', date('Y'), $sequence);
    }

    /**
     * The shared allocation path. Assumes it is already inside a transaction.
     *
     * @param  array<int, numeric-string|float|int>  $allocations
     */
    private function applyAllocations(Payment $payment, array $allocations): void
    {
        $unallocated = $payment->unallocatedAmount();

        foreach ($allocations as $invoiceId => $rawAmount) {
            $amount = $this->normalise($rawAmount);

            if (bccomp($amount, '0', 2) !== 1) {
                continue;
            }

            // Locked for the rest of the transaction so a concurrent payment
            // cannot read the same balance and over-apply against it.
            $invoice = Invoice::whereKey($invoiceId)->lockForUpdate()->first();

            if (! $invoice) {
                throw new DomainException("Invoice {$invoiceId} could not be found.");
            }

            if ($invoice->customer_id !== $payment->customer_id) {
                throw new DomainException(
                    "Invoice {$invoice->invoice_number} belongs to a different customer."
                );
            }

            if (! $invoice->status->acceptsPayment()) {
                throw new DomainException(
                    "Invoice {$invoice->invoice_number} is {$invoice->status->label()} and cannot take a payment."
                );
            }

            if (bccomp($amount, $unallocated, 2) === 1) {
                throw new DomainException(
                    'The allocated amounts add up to more than the payment.'
                );
            }

            if (bccomp($amount, (string) $invoice->balance_due, 2) === 1) {
                throw new DomainException(
                    "Applying {$amount} to invoice {$invoice->invoice_number} exceeds its balance of {$invoice->balance_due}."
                );
            }

            // Topping up the same invoice adjusts the existing row rather than
            // adding a second one; the unique index expects exactly that.
            $allocation = PaymentAllocation::firstOrNew([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
            ]);

            $allocation->amount = bcadd((string) ($allocation->amount ?? '0'), $amount, 2);
            $allocation->save();

            $payment->forceFill([
                'allocated_amount' => bcadd((string) $payment->allocated_amount, $amount, 2),
            ])->save();

            $unallocated = bcsub($unallocated, $amount, 2);

            $this->invoices->recalculate($invoice);
        }
    }

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
