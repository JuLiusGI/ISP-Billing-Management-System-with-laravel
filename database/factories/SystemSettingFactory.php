<?php

namespace Database\Factories;

use App\Enums\SettingType;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        $key = 'test.'.fake()->unique()->word();

        return [
            'group' => 'billing',
            'key' => $key,
            'value' => fake()->word(),
            'type' => SettingType::String,
            'label' => ucfirst(str_replace('.', ' ', $key)),
            'description' => null,
        ];
    }

    public function ofType(SettingType $type, ?string $value): static
    {
        return $this->state(fn () => ['type' => $type, 'value' => $value]);
    }
}
