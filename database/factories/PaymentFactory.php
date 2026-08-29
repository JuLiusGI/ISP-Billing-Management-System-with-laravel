<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $amount = fake()->randomElement([999, 1299, 1499, 1999, 2499]);

        return [
            'payment_reference' => 'PAY-'.fake()->unique()->numerify('########'),
            'customer_id' => Customer::factory(),
            'payment_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'amount' => $amount,
            'allocated_amount' => 0,
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'reference_number' => fake()->optional(0.5)->numerify('REF#########'),
            'notes' => null,
            'status' => PaymentStatus::Completed,
        ];
    }

    public function ofAmount(float $amount): static
    {
        return $this->state(fn () => ['amount' => $amount]);
    }

    public function method(PaymentMethod $method): static
    {
        return $this->state(fn () => ['payment_method' => $method]);
    }

    public function reversed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Reversed,
            'reversed_at' => now(),
            'reversal_reason' => 'Bounced cheque',
        ]);
    }
}
