<?php

namespace Database\Factories;

use App\Enums\InvoiceItemType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $quantity = 1;
        $unitPrice = fake()->randomElement([999, 1299, 1499, 1999]);

        return [
            'invoice_id' => Invoice::factory(),
            'description' => 'Monthly internet service',
            'item_type' => InvoiceItemType::Subscription,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => 0,
            'line_total' => bcmul((string) $quantity, (string) $unitPrice, 2),
        ];
    }

    public function ofType(InvoiceItemType $type, string $description, float $price): static
    {
        return $this->state(fn () => [
            'item_type' => $type,
            'description' => $description,
            'unit_price' => $price,
            'quantity' => 1,
            'line_total' => number_format($price, 2, '.', ''),
        ]);
    }
}
