<?php

namespace App\Models;

use App\Enums\CustomerAccountStatus;
use App\Enums\CustomerConnectionStatus;
use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'account_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'gender',
        'date_of_birth',
        'contact_number',
        'alternate_contact_number',
        'email',
        'photo_path',
        'customer_type',
        'installation_date',
        'status',
        'account_status',
        'connection_status',
        'notes',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'installation_date' => 'date',
            'customer_type' => CustomerType::class,
            'status' => CustomerStatus::class,
            'account_status' => CustomerAccountStatus::class,
            'connection_status' => CustomerConnectionStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // Account numbers are generated rather than entered. The unique index
        // is the real guarantee; the service layer retries on collision.
        static::creating(function (Customer $customer): void {
            $customer->account_number ??= static::nextAccountNumber();
        });
    }

    public static function nextAccountNumber(): string
    {
        $sequence = (static::withTrashed()->max('id') ?? 0) + 1;

        return sprintf('ACC-%s-%05d', date('Y'), $sequence);
    }

    // -----------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------

    /** @return HasMany<CustomerAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /** @return HasOne<CustomerAddress, $this> */
    public function primaryAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)->where('is_primary', true);
    }

    /** @return HasMany<CustomerContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<ServiceStatusLog, $this> */
    public function serviceStatusLogs(): HasMany
    {
        return $this->hasMany(ServiceStatusLog::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -----------------------------------------------------------------
    // Attributes
    // -----------------------------------------------------------------

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ])));
    }

    /**
     * What this customer still owes, summed from live invoice balances.
     * Cancelled and void invoices are excluded by the status filter.
     */
    public function outstandingBalance(): string
    {
        return (string) $this->invoices()
            ->whereIn('status', array_map(fn (InvoiceStatus $s) => $s->value, InvoiceStatus::outstanding()))
            ->sum('balance_due');
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    /** @param  Builder<Customer>  $query */
    public function scopeStatus(Builder $query, CustomerStatus|string $status): void
    {
        $query->where('status', $status instanceof CustomerStatus ? $status->value : $status);
    }

    /** @param  Builder<Customer>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CustomerStatus::Active);
    }

    /**
     * Server-side search across the fields a staff member would actually type.
     *
     * @param  Builder<Customer>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.$term.'%';

        $query->where(function (Builder $q) use ($like): void {
            $q->where('account_number', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('contact_number', 'like', $like);
        });
    }
}
