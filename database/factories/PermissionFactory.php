<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $module = fake()->randomElement(['Customers', 'Billing', 'Payments', 'Reports']);
        $ability = fake()->word().fake()->unique()->numerify('#####');

        return [
            'name' => strtolower($module).'.'.$ability,
            'display_name' => ucfirst($ability).' '.$module,
            'module' => $module,
            'description' => null,
        ];
    }
}
