<?php

namespace App\Http\Requests\Concerns;

use App\Enums\ConnectionType;
use App\Models\Subscription;
use Illuminate\Validation\Rule;

trait HandlesSubscriptionInput
{
    protected function prepareForValidation(): void
    {
        // Empty strings from unfilled optional inputs should be null, not ''.
        foreach (['static_ip', 'username', 'expiration_date', 'activation_date'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $subscription = $this->route('subscription');
        $id = $subscription instanceof Subscription ? $subscription->id : null;

        return [
            // The customer is fixed once the subscription exists: moving a line
            // between customers would detach it from its billing history.
            'customer_id' => [
                $id ? 'sometimes' : 'required',
                Rule::exists('customers', 'id')->whereNull('deleted_at'),
            ],
            'internet_plan_id' => ['required', Rule::exists('internet_plans', 'id')->whereNull('deleted_at')],

            'start_date' => ['required', 'date'],
            'activation_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'expiration_date' => ['nullable', 'date', 'after:start_date'],

            'billing_day' => ['required', 'integer', 'min:1', 'max:31'],

            'monthly_rate' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'installation_fee' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'discount_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'lte:monthly_rate'],

            'connection_type' => ['required', Rule::enum(ConnectionType::class)],
            'static_ip' => ['nullable', 'ip'],
            'username' => [
                'nullable', 'string', 'max:80',
                Rule::unique('subscriptions', 'username')->ignore($id)->whereNull('deleted_at'),
            ],
            'service_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'discount_amount.lte' => 'The discount cannot exceed the monthly rate.',
            'username.unique' => 'Another subscription already uses that username.',
            'billing_day.max' => 'The billing day must be between 1 and 31.',
            'expiration_date.after' => 'The expiration date must fall after the start date.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'internet_plan_id' => 'internet plan',
            'customer_id' => 'customer',
            'billing_day' => 'billing day',
            'monthly_rate' => 'monthly rate',
        ];
    }
}
