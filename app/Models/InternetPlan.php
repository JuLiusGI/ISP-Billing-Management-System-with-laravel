<?php

namespace App\Models;

use App\Enums\PlanBillingCycle;
use App\Enums\SpeedUnit;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InternetPlan extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'plan_code',
        'name',
        'download_speed',
        'upload_speed',
        'speed_unit',
        'monthly_price',
        'installation_fee',
        'activation_fee',
        'billing_cycle',
        'description',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'download_speed' => 'integer',
            'upload_speed' => 'integer',
            'speed_unit' => SpeedUnit::class,
            // Cast as decimal strings, not floats, so money keeps its precision.
            'monthly_price' => 'decimal:2',
            'installation_fee' => 'decimal:2',
            'activation_fee' => 'decimal:2',
            'billing_cycle' => PlanBillingCycle::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Subscriptions that were created from this plan.
     *
     * Note these do not track the plan's current price: each subscription
     * copies monthly_rate at signup, so repricing here leaves existing
     * customers and their invoice history untouched.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function getSpeedLabelAttribute(): string
    {
        return "{$this->download_speed}/{$this->upload_speed} {$this->speed_unit->value}";
    }

    /** @param  Builder<InternetPlan>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    // -----------------------------------------------------------------
    // Audit trail
    // -----------------------------------------------------------------

    protected function auditModule(): string
    {
        return 'Internet Plans';
    }

    protected function auditLabel(): string
    {
        return $this->plan_code.' - '.$this->name;
    }
}
