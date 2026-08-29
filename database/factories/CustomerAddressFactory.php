<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => 'service',
            'house_building' => fake()->buildingNumber(),
            'street' => fake()->streetName(),
            'barangay' => 'Barangay '.fake()->numberBetween(1, 60),
            'municipality_city' => fake()->city(),
            'province' => fake()->state(),
            'postal_code' => fake()->numerify('####'),
            'is_primary' => true,
        ];
    }

    public function billing(): static
    {
        return $this->state(fn () => ['type' => 'billing', 'is_primary' => false]);
    }
}
