<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return match ($this->group()) {
            'company' => [
                'company_name' => ['required', 'string', 'max:120'],
                'company_address' => ['nullable', 'string', 'max:255'],
                'company_phone' => ['nullable', 'string', 'max:40'],
                'company_email' => ['nullable', 'email', 'max:255'],
                'company_website' => ['nullable', 'string', 'max:255'],
                'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:1024'],
                'remove_logo' => ['nullable', 'boolean'],
            ],
            'billing' => [
                'default_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'semi_annual', 'annual'])],
                // Zero is a valid grace period: due on issue.
                'grace_period_days' => ['required', 'integer', 'min:0', 'max:120'],
                'invoice_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9-]+$/'],
                'receipt_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9-]+$/'],
                'currency' => ['required', 'string', 'size:3', 'alpha'],
                'currency_symbol' => ['required', 'string', 'max:5'],
                'tax_enabled' => ['required', 'boolean'],
                'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            ],
            'service' => [
                'auto_suspend_enabled' => ['required', 'boolean'],
                'suspend_after_days_overdue' => ['required', 'integer', 'min:1', 'max:365'],
                'default_status' => ['required', Rule::in(['pending', 'active'])],
            ],
            'notifications' => [
                'email_enabled' => ['required', 'boolean'],
                'on_invoice_created' => ['required', 'boolean'],
                'on_payment_received' => ['required', 'boolean'],
                'on_invoice_overdue' => ['required', 'boolean'],
                'on_service_suspended' => ['required', 'boolean'],
                'on_service_reactivated' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'invoice_prefix.regex' => 'The invoice prefix may contain letters, numbers and hyphens only.',
            'receipt_prefix.regex' => 'The receipt prefix may contain letters, numbers and hyphens only.',
            'currency.size' => 'Use the three-letter currency code, such as PHP.',
        ];
    }

    /** Which settings tab was submitted. */
    public function group(): string
    {
        return (string) $this->route('group');
    }
}
