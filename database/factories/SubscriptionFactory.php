<?php

namespace Database\Factories;

use App\Enums\ConnectionType;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 years', '-1 month');

        return [
            // subscription_code is left out on purpose: the model generates it.
            'customer_id' => Customer::factory(),
            'internet_plan_id' => InternetPlan::factory(),
            'start_date' => $start,
            'activation_date' => $start,
            'expiration_date' => null,
            'billing_day' => fake()->numberBetween(1, 28),
            'monthly_rate' => fake()->randomElement([999, 1299, 1499, 1999, 2499]),
            'installation_fee' => 0,
            'discount_amount' => 0,
            'status' => SubscriptionStatus::Active,
            'connection_type' => ConnectionType::Fiber,
            'static_ip' => null,
            'username' => fake()->userName().fake()->unique()->numerify('#####').'@isp',
            'service_notes' => null,
        ];
    }

    /**
     * Ties the subscription to a real plan and copies its pricing across, the
     * same way the application does at signup.
     */
    public function forPlan(InternetPlan $plan): static
    {
        return $this->state(fn () => [
            'internet_plan_id' => $plan->id,
            'monthly_rate' => $plan->monthly_price,
            'installation_fee' => $plan->installation_fee,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Pending,
            'activation_date' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => SubscriptionStatus::Suspended]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => SubscriptionStatus::Cancelled]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Expired,
            'expiration_date' => now()->subDays(fake()->numberBetween(1, 90)),
        ]);
    }

    public function discounted(float $amount): static
    {
        return $this->state(fn () => ['discount_amount' => $amount]);
    }
}
