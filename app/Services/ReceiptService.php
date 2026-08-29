<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Issuing receipts for payments.
 *
 * A receipt acknowledges money received, so exactly one belongs to each
 * payment — the unique index on payment_id says so, and issuing is idempotent
 * rather than an error when someone presses the button twice.
 */
class ReceiptService
{
    private const NUMBER_ATTEMPTS = 5;

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Issues the receipt for a payment, or returns the one already issued.
     *
     * @throws DomainException when the payment is not money the ISP holds
     */
    public function issue(Payment $payment, ?User $actor = null): Receipt
    {
        if ($existing = $payment->receipt()->first()) {
            return $existing;
        }

        if ($payment->status !== PaymentStatus::Completed) {
            throw new DomainException(
                'A receipt can only be issued for a completed payment.'
            );
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn () => Receipt::create([
                    'receipt_number' => $this->nextReceiptNumber(),
                    'payment_id' => $payment->id,
                    'issued_by' => $actor?->id,
                    'issued_at' => now(),
                ]));
            } catch (UniqueConstraintViolationException $e) {
                // Another request issued this payment's receipt first: return
                // theirs rather than failing.
                if ($existing = $payment->receipt()->first()) {
                    return $existing;
                }

                if ($attempt >= self::NUMBER_ATTEMPTS || ! str_contains($e->getMessage(), 'receipt_number')) {
                    throw $e;
                }
            }
        }
    }

    public function nextReceiptNumber(): string
    {
        $sequence = (Receipt::max('id') ?? 0) + 1;

        return sprintf('%s-%s-%06d', $this->settings->receiptPrefix(), date('Y'), $sequence);
    }

    /**
     * The company block printed at the head of a receipt.
     *
     * @return array<string, string>
     */
    public function companyDetails(): array
    {
        return [
            'name' => $this->settings->string('company.name', config('app.name')),
            'address' => $this->settings->string('company.address'),
            'phone' => $this->settings->string('company.phone'),
            'email' => $this->settings->string('company.email'),
            'website' => $this->settings->string('company.website'),
        ];
    }
}
