<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerContact>
 */
class CustomerContactFactory extends Factory
{
    protected $model = CustomerContact::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->name(),
            'relationship' => fake()->randomElement(['Spouse', 'Parent', 'Sibling', 'Caretaker']),
            'contact_number' => fake()->numerify('09#########'),
            'email' => fake()->optional(0.5)->safeEmail(),
            'is_primary' => false,
        ];
    }
}
