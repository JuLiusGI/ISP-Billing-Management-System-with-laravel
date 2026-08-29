<?php

namespace App\Http\Requests\Concerns;

use App\Enums\InvoiceItemType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait HandlesInvoiceInput
{
    protected function prepareForValidation(): void
    {
        // The dynamic item rows leave gaps in the index when one is removed;
        // reindex so items.0, items.1 … stay contiguous for the error bag.
        if (is_array($this->input('items'))) {
            $this->merge(['items' => array_values(array_filter(
                $this->input('items'),
                fn ($row) => filled($row['description'] ?? null) || filled($row['unit_price'] ?? null)
            ))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'subscription_id' => ['nullable', Rule::exists('subscriptions', 'id')->whereNull('deleted_at')],

            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],

            'billing_period_start' => ['nullable', 'date'],
            'billing_period_end' => ['nullable', 'date', 'after_or_equal:billing_period_start'],

            'discount_total' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'charges_total' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.item_type' => ['required', Rule::enum(InvoiceItemType::class)],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:99999', 'decimal:0,2'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'items.*.discount_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = (array) $this->input('items', []);
            $lineTotal = '0.00';

            foreach ($items as $index => $item) {
                $quantity = (string) ($item['quantity'] ?? 0);
                $price = (string) ($item['unit_price'] ?? 0);
                $discount = (string) ($item['discount_amount'] ?? 0);

                if (! is_numeric($quantity) || ! is_numeric($price) || ! is_numeric($discount)) {
                    continue;
                }

                $gross = bcmul($quantity, $price, 2);

                // A line cannot be discounted below zero.
                if (bccomp($discount, $gross, 2) === 1) {
                    $validator->errors()->add(
                        "items.{$index}.discount_amount",
                        'The line discount cannot exceed the line total.'
                    );
                }

                $lineTotal = bcadd($lineTotal, bcsub($gross, $discount, 2), 2);
            }

            // Nor can the invoice-level discount exceed what is left.
            $invoiceDiscount = (string) $this->input('discount_total', 0);

            if (is_numeric($invoiceDiscount) && bccomp($invoiceDiscount, $lineTotal, 2) === 1) {
                $validator->errors()->add(
                    'discount_total',
                    'The invoice discount cannot exceed the total of the line items.'
                );
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'discount_total' => 'invoice discount',
            'charges_total' => 'additional charges',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function lineItems(): array
    {
        return array_map(fn (array $item) => [
            'description' => $item['description'],
            'item_type' => $item['item_type'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'discount_amount' => $item['discount_amount'],
        ], (array) $this->safe()->input('items', []));
    }

    /** @return array<string, mixed> */
    public function invoiceAttributes(): array
    {
        return $this->safe()->only([
            'subscription_id', 'invoice_date', 'due_date',
            'billing_period_start', 'billing_period_end',
            'discount_total', 'charges_total', 'notes',
        ]);
    }
}
