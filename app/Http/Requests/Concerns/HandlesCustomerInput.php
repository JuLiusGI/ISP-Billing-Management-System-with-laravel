<?php

namespace App\Http\Requests\Concerns;

use App\Enums\CustomerAccountStatus;
use App\Enums\CustomerConnectionStatus;
use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Validation\Rule;

/**
 * Validation and input shaping shared by the customer store and update
 * requests, so the two can never drift apart.
 */
trait HandlesCustomerInput
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],

            'contact_number' => ['required', 'string', 'max:30'],
            'alternate_contact_number' => ['nullable', 'string', 'max:30'],
            'email' => [
                'nullable', 'email', 'max:255',
                // Archived customers keep their address; only live rows collide.
                Rule::unique('customers', 'email')
                    ->ignore($customer instanceof Customer ? $customer->id : null)
                    ->whereNull('deleted_at'),
            ],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'customer_type' => ['required', Rule::enum(CustomerType::class)],
            'installation_date' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(CustomerStatus::class)],
            'account_status' => ['required', Rule::enum(CustomerAccountStatus::class)],
            'connection_status' => ['required', Rule::enum(CustomerConnectionStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],

            'address.house_building' => ['nullable', 'string', 'max:120'],
            'address.street' => ['nullable', 'string', 'max:120'],
            'address.barangay' => ['required', 'string', 'max:120'],
            'address.municipality_city' => ['required', 'string', 'max:120'],
            'address.province' => ['required', 'string', 'max:120'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],

            'contacts' => ['nullable', 'array', 'max:5'],
            'contacts.*.name' => ['nullable', 'required_with:contacts.*.contact_number', 'string', 'max:160'],
            'contacts.*.relationship' => ['nullable', 'string', 'max:60'],
            'contacts.*.contact_number' => ['nullable', 'required_with:contacts.*.name', 'string', 'max:30'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'address.house_building' => 'house/building',
            'address.street' => 'street',
            'address.barangay' => 'barangay',
            'address.municipality_city' => 'municipality/city',
            'address.province' => 'province',
            'address.postal_code' => 'postal code',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contacts.*.name.required_with' => 'Give this additional contact a name, or clear the row.',
            'contacts.*.contact_number.required_with' => 'Give this additional contact a number, or clear the row.',
            'email.unique' => 'Another customer already uses that email address.',
        ];
    }

    /**
     * The customer's own columns. account_number is never accepted from input;
     * it is generated.
     *
     * @return array<string, mixed>
     */
    public function customerAttributes(): array
    {
        return $this->safe()->only([
            'first_name', 'middle_name', 'last_name', 'suffix', 'gender', 'date_of_birth',
            'contact_number', 'alternate_contact_number', 'email',
            'customer_type', 'installation_date', 'status', 'account_status',
            'connection_status', 'notes',
        ]);
    }

    /** @return array<string, mixed> */
    public function addressAttributes(): array
    {
        return (array) $this->safe()->input('address', []);
    }

    /** @return array<int, array<string, mixed>> */
    public function contactAttributes(): array
    {
        return (array) $this->safe()->input('contacts', []);
    }
}
