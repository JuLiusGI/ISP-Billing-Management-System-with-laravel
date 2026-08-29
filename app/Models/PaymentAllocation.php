<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a payment to one invoice for a given amount. This is the join that
 * lets one payment settle several invoices and lets an invoice be settled by
 * several payments, and it is the source of truth for invoice balances.
 */
class PaymentAllocation extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'payment_id',
        'invoice_id',
        'amount',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
