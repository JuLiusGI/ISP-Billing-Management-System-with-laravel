<?php

namespace Database\Factories;

use App\Enums\CustomerAccountStatus;
use App\Enums\CustomerConnectionStatus;
use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        // account_number is left out on purpose: the model generates it.
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional(0.6)->lastName(),
            'last_name' => fake()->lastName(),
            'suffix' => fake()->optional(0.1)->randomElement(['Jr.', 'Sr.', 'III']),
            'gender' => fake()->randomElement(['male', 'female']),
            'date_of_birth' => fake()->dateTimeBetween('-65 years', '-18 years'),
            'contact_number' => fake()->numerify('09#########'),
            'alternate_contact_number' => fake()->optional(0.3)->numerify('09#########'),
            'email' => fake()->boolean(80) ? fake()->unique()->safeEmail() : null,
            'customer_type' => CustomerType::Residential,
            'installation_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'status' => CustomerStatus::Active,
            'account_status' => CustomerAccountStatus::GoodStanding,
            'connection_status' => CustomerConnectionStatus::Connected,
            'notes' => null,
        ];
    }

    public function pendingInstallation(): static
    {
        return $this->state(fn () => [
            'status' => CustomerStatus::PendingInstallation,
            'connection_status' => CustomerConnectionStatus::Pending,
            'installation_date' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => CustomerStatus::Suspended,
            'account_status' => CustomerAccountStatus::Overdue,
            'connection_status' => CustomerConnectionStatus::Disconnected,
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn () => [
            'status' => CustomerStatus::Terminated,
            'connection_status' => CustomerConnectionStatus::Disconnected,
        ]);
    }

    public function business(): static
    {
        return $this->state(fn () => ['customer_type' => CustomerType::Business]);
    }
}
