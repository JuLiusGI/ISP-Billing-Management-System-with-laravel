<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        $base = fake()->randomElement([
            'Internet Upstream', 'Electricity', 'Equipment', 'Maintenance',
            'Salaries', 'Transportation', 'Office Supplies', 'Other',
        ]);

        // The suffix carries the uniqueness. Drawing a unique value from the
        // fixed list above would exhaust the pool once a run needs a ninth row.
        $suffix = fake()->unique()->numerify('#####');

        return [
            'name' => "{$base} {$suffix}",
            'code' => strtoupper(str_replace(' ', '_', $base)).'-'.$suffix,
            'description' => null,
            'is_active' => true,
        ];
    }
}
