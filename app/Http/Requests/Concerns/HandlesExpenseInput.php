<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

/**
 * Shared by the expense store and update requests so the two cannot drift.
 */
trait HandlesExpenseInput
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expense_category_id' => $this->categoryRule(),
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'vendor' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * A new expense may only be filed under a category still in use.
     * The update request widens this to keep a retired category editable.
     *
     * @return array<int, mixed>
     */
    protected function categoryRule(): array
    {
        return [
            'required', 'integer',
            Rule::exists('expense_categories', 'id')->where('is_active', true),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'expense_category_id' => 'category',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.gt' => 'An expense must be for more than zero.',
            'expense_date.before_or_equal' => 'An expense cannot be dated in the future.',
            'expense_category_id.exists' => 'Choose an active expense category.',
        ];
    }
}
