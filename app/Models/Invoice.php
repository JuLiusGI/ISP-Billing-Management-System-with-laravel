<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\Auditable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'invoice_number',
        'customer_id',
        'subscription_id',
        'billing_cycle_id',
        'billing_period_start',
        'billing_period_end',
        'invoice_date',
        'due_date',
        'subtotal',
        'discount_total',
        'charges_total',
        'tax_total',
        'total_amount',
        'amount_paid',
        'balance_due',
        'status',
        'notes',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'charges_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'status' => InvoiceStatus::class,
        ];
    }

    // -----------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<BillingCycle, $this> */
    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Payments that touched this invoice, reached through their allocations.
     *
     * @return HasManyThrough<Payment, PaymentAllocation, $this>
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Payment::class,
            PaymentAllocation::class,
            'invoice_id',
            'id',
            'id',
            'payment_id'
        );
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // -----------------------------------------------------------------
    // Derived values
    //
    // amount_paid and balance_due are stored on the row for fast listing and
    // reporting, but allocations are the source of truth. These helpers read
    // straight from the allocations so the stored figures can be checked.
    // -----------------------------------------------------------------

    /** Total settled against this invoice by payments that still count. */
    public function allocatedTotal(): string
    {
        return Money::of($this->allocations()
            ->whereHas('payment', fn (Builder $q) => $q->where('status', PaymentStatus::Completed))
            ->sum('amount'));
    }

    /** Invoice total less valid allocated payments, floored at zero. */
    public function calculatedBalance(): string
    {
        return Money::atLeastZero(
            bcsub((string) $this->total_amount, $this->allocatedTotal(), 2)
        );
    }

    public function isOverdue(): bool
    {
        return $this->status->isOutstanding()
            && $this->due_date !== null
            && $this->due_date->isPast();
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? $this->due_date->diffInDays(now()) : 0;
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    /** @param  Builder<Invoice>  $query */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereIn('status', array_map(
            fn (InvoiceStatus $s) => $s->value,
            InvoiceStatus::outstanding()
        ));
    }

    /** @param  Builder<Invoice>  $query */
    public function scopeOverdue(Builder $query): void
    {
        $query->outstanding()->whereDate('due_date', '<', now());
    }

    /** @param  Builder<Invoice>  $query */
    public function scopeStatus(Builder $query, InvoiceStatus|string $status): void
    {
        $query->where('status', $status instanceof InvoiceStatus ? $status->value : $status);
    }

    /** @param  Builder<Invoice>  $query */
    public function scopeIssuedBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): void
    {
        $query->whereBetween('invoice_date', [$from, $to]);
    }

    // -----------------------------------------------------------------
    // Audit trail
    // -----------------------------------------------------------------

    protected function auditModule(): string
    {
        return 'Billing';
    }

    protected function auditLabel(): string
    {
        return $this->invoice_number;
    }
}
