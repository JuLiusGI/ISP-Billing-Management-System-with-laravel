<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceStatusLog extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'subscription_id',
        'customer_id',
        'from_status',
        'to_status',
        'reason',
        'notes',
        'changed_by',
        'is_automatic',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_automatic' => 'boolean',
        ];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // -----------------------------------------------------------------
    // Scopes for the service status history
    // -----------------------------------------------------------------

    /** @param  Builder<ServiceStatusLog>  $query */
    public function scopeMovedTo(Builder $query, SubscriptionStatus|string $status): void
    {
        $query->where('to_status', $status instanceof SubscriptionStatus ? $status->value : $status);
    }

    /** @param  Builder<ServiceStatusLog>  $query */
    public function scopeMovedFrom(Builder $query, SubscriptionStatus|string $status): void
    {
        $query->where('from_status', $status instanceof SubscriptionStatus ? $status->value : $status);
    }

    /**
     * Separates scheduler-driven changes from ones a person made, which is the
     * question asked most often when a customer disputes a disconnection.
     *
     * @param  Builder<ServiceStatusLog>  $query
     */
    public function scopeBySource(Builder $query, string $source): void
    {
        match ($source) {
            'automatic' => $query->where('is_automatic', true),
            'manual' => $query->where('is_automatic', false),
            default => null,
        };
    }

    /** @param  Builder<ServiceStatusLog>  $query */
    public function scopeRecordedBetween(Builder $query, ?string $from, ?string $to): void
    {
        $query->when($from, fn (Builder $q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('created_at', '<=', $to));
    }

    /**
     * Matches the customer or the subscription the change belongs to.
     *
     * @param  Builder<ServiceStatusLog>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.$term.'%';

        $query->where(function (Builder $q) use ($like): void {
            $q->whereHas('customer', fn (Builder $c) => $c
                ->where('account_number', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like))
                ->orWhereHas('subscription', fn (Builder $s) => $s
                    ->where('subscription_code', 'like', $like)
                    ->orWhere('username', 'like', $like));
        });
    }

    /** The from/to values as enums where they map, for consistent labelling. */
    public function fromStatus(): ?SubscriptionStatus
    {
        return $this->from_status ? SubscriptionStatus::tryFrom($this->from_status) : null;
    }

    public function toStatus(): ?SubscriptionStatus
    {
        return SubscriptionStatus::tryFrom($this->to_status);
    }
}
