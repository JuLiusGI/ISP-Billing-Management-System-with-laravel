<?php

namespace App\Models;

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
}
