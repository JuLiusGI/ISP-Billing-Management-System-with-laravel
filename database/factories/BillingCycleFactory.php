<?php

namespace Database\Factories;

use App\Enums\BillingCycleStatus;
use App\Models\BillingCycle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<BillingCycle>
 */
class BillingCycleFactory extends Factory
{
    protected $model = BillingCycle::class;

    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('-1 year', 'now'))->startOfMonth();

        return [
            'name' => $start->format('F Y'),
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'due_date' => $start->copy()->addDays(15)->toDateString(),
            'status' => BillingCycleStatus::Open,
        ];
    }

    public function forMonth(Carbon $month): static
    {
        $start = $month->copy()->startOfMonth();

        return $this->state(fn () => [
            'name' => $start->format('F Y'),
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'due_date' => $start->copy()->addDays(15)->toDateString(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => BillingCycleStatus::Closed,
            'generated_at' => now(),
        ]);
    }
}
