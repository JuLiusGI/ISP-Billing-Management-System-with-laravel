<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = fake()->word().'-'.fake()->unique()->numerify('#####');

        return [
            'name' => Str::slug($name),
            'display_name' => Str::headline($name),
            'description' => null,
            'is_system' => false,
        ];
    }
}
