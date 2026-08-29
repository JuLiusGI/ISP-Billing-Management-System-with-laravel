<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Payment::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        // The allocation grid posts a box per outstanding invoice; blanks and
        // zeroes mean "nothing to this one".
        $this->merge([
            'allocations' => array_filter(
                (array) $this->input('allocations', []),
                fn ($amount) => is_numeric($amount) && (float) $amount > 0
            ),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'allocations' => ['array'],
            'allocations.*' => ['numeric', 'gt:0', 'decimal:0,2'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $amount = (string) $this->input('amount', 0);
            $allocated = '0.00';

            foreach ((array) $this->input('allocations', []) as $value) {
                if (is_numeric($value)) {
                    $allocated = bcadd($allocated, (string) $value, 2);
                }
            }

            if (! is_numeric($amount)) {
                return;
            }

            // The remainder is legitimate — it becomes customer credit — but
            // applying more than was received is not.
            if (bccomp($allocated, bcadd($amount, '0', 2), 2) === 1) {
                $validator->errors()->add(
                    'allocations',
                    'The amounts applied to invoices add up to more than the payment.'
                );
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'payment_date.before_or_equal' => 'A payment cannot be dated in the future.',
            'amount.gt' => 'A payment must be for more than zero.',
        ];
    }

    /** @return array<int, string> */
    public function allocations(): array
    {
        return (array) $this->safe()->input('allocations', []);
    }
}
