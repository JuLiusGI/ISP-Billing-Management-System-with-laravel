<?php

namespace App\Models;

use App\Enums\ConnectionType;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'subscription_code',
        'customer_id',
        'internet_plan_id',
        'start_date',
        'activation_date',
        'expiration_date',
        'billing_day',
        'monthly_rate',
        'installation_fee',
        'discount_amount',
        'status',
        'connection_type',
        'static_ip',
        'username',
        'service_notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'activation_date' => 'date',
            'expiration_date' => 'date',
            'billing_day' => 'integer',
            'monthly_rate' => 'decimal:2',
            'installation_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'status' => SubscriptionStatus::class,
            'connection_type' => ConnectionType::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription): void {
            $subscription->subscription_code ??= static::nextSubscriptionCode();
        });
    }

    public static function nextSubscriptionCode(): string
    {
        $sequence = (static::withTrashed()->max('id') ?? 0) + 1;

        return sprintf('SUB-%s-%05d', date('Y'), $sequence);
    }

    // -----------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<InternetPlan, $this> */
    public function internetPlan(): BelongsTo
    {
        return $this->belongsTo(InternetPlan::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<ServiceStatusLog, $this> */
    public function serviceStatusLogs(): HasMany
    {
        return $this->hasMany(ServiceStatusLog::class);
    }

    // -----------------------------------------------------------------
    // Attributes
    // -----------------------------------------------------------------

    /** What this subscription bills per period, after its standing discount. */
    public function getNetMonthlyRateAttribute(): string
    {
        return bcsub((string) $this->monthly_rate, (string) $this->discount_amount, 2);
    }

    public function isExpired(): bool
    {
        return $this->expiration_date !== null && $this->expiration_date->isPast();
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    /** @param  Builder<Subscription>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', SubscriptionStatus::Active);
    }

    /** @param  Builder<Subscription>  $query */
    public function scopeStatus(Builder $query, SubscriptionStatus|string $status): void
    {
        $query->where('status', $status instanceof SubscriptionStatus ? $status->value : $status);
    }

    /**
     * Subscriptions the billing run should invoice on a given day of the month.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeBillableOn(Builder $query, int $dayOfMonth): void
    {
        $query->where('status', SubscriptionStatus::Active)
            ->where('billing_day', $dayOfMonth);
    }

    /** @param  Builder<Subscription>  $query */
    public function scopeExpiringBefore(Builder $query, \DateTimeInterface $date): void
    {
        $query->whereNotNull('expiration_date')->where('expiration_date', '<=', $date);
    }

    // -----------------------------------------------------------------
    // Audit trail
    // -----------------------------------------------------------------

    protected function auditModule(): string
    {
        return 'Subscriptions';
    }

    protected function auditLabel(): string
    {
        return $this->subscription_code;
    }
}
