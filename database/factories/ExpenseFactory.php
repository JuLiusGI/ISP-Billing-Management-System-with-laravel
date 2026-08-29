<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'expense_reference' => 'EXP-'.fake()->unique()->numerify('########'),
            'expense_category_id' => ExpenseCategory::factory(),
            'description' => fake()->sentence(4),
            'amount' => fake()->randomFloat(2, 500, 50000),
            'expense_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'payment_method' => PaymentMethod::Cash,
            'vendor' => fake()->optional(0.7)->company(),
            'notes' => null,
        ];
    }

    public function ofAmount(float $amount): static
    {
        return $this->state(fn () => ['amount' => $amount]);
    }
}
