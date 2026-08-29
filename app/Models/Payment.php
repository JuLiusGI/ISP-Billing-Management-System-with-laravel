<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'payment_reference',
        'customer_id',
        'payment_date',
        'amount',
        'allocated_amount',
        'payment_method',
        'reference_number',
        'received_by',
        'notes',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'reversed_at' => 'datetime',
            'amount' => 'decimal:2',
            'allocated_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
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

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return HasOne<Receipt, $this> */
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    // -----------------------------------------------------------------
    // Derived values
    // -----------------------------------------------------------------

    /**
     * Money received but not yet applied to any invoice. This is how an
     * overpayment is held: as customer credit, not as a negative balance.
     */
    public function unallocatedAmount(): string
    {
        return bcsub((string) $this->amount, (string) $this->allocated_amount, 2);
    }

    public function isFullyAllocated(): bool
    {
        return bccomp($this->unallocatedAmount(), '0', 2) === 0;
    }

    public function isReversed(): bool
    {
        return $this->status === PaymentStatus::Reversed;
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    /** Payments that still count as money received. @param Builder<Payment> $query */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', PaymentStatus::Completed);
    }

    /** @param  Builder<Payment>  $query */
    public function scopeReceivedBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): void
    {
        $query->whereBetween('payment_date', [$from, $to]);
    }

    /** @param  Builder<Payment>  $query */
    public function scopeMethod(Builder $query, PaymentMethod|string $method): void
    {
        $query->where('payment_method', $method instanceof PaymentMethod ? $method->value : $method);
    }
}
