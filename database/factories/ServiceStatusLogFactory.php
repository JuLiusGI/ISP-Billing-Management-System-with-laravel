<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\ServiceStatusLog;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceStatusLog>
 */
class ServiceStatusLogFactory extends Factory
{
    protected $model = ServiceStatusLog::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'customer_id' => Customer::factory(),
            'from_status' => SubscriptionStatus::Active->value,
            'to_status' => SubscriptionStatus::Suspended->value,
            'reason' => 'Non-payment',
            'notes' => null,
            'is_automatic' => false,
        ];
    }

    /** Keeps the log pointed at the subscription's own customer. */
    public function forSubscription(Subscription $subscription): static
    {
        return $this->state(fn () => [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
        ]);
    }

    public function automatic(): static
    {
        return $this->state(fn () => ['is_automatic' => true]);
    }
}
