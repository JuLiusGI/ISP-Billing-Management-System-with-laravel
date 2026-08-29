<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    public function definition(): array
    {
        return [
            'receipt_number' => 'OR-'.fake()->unique()->numerify('########'),
            'payment_id' => Payment::factory(),
            'issued_at' => now(),
        ];
    }
}
