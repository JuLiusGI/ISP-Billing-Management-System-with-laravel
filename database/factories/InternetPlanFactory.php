<?php

namespace Database\Factories;

use App\Enums\PlanBillingCycle;
use App\Enums\SpeedUnit;
use App\Models\InternetPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternetPlan>
 */
class InternetPlanFactory extends Factory
{
    protected $model = InternetPlan::class;

    public function definition(): array
    {
        $speed = fake()->randomElement([25, 50, 100, 200, 300, 500]);
        $tier = $speed >= 300 ? 'Business' : 'Home';

        return [
            'plan_code' => strtoupper(substr($tier, 0, 3)).'-'.$speed.'-'.fake()->unique()->numerify('######'),
            'name' => "{$tier} {$speed} Mbps",
            'download_speed' => $speed,
            'upload_speed' => $speed,
            'speed_unit' => SpeedUnit::Mbps,
            'monthly_price' => $speed * 15,
            'installation_fee' => fake()->randomElement([0, 1500, 2500]),
            'activation_fee' => 0,
            'billing_cycle' => PlanBillingCycle::Monthly,
            'description' => "{$tier} fiber plan at {$speed} Mbps.",
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function priced(float $monthly): static
    {
        return $this->state(fn () => ['monthly_price' => $monthly]);
    }
}
