<?php

namespace App\Http\Requests;

use App\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeSubscriptionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageStatus', $this->route('subscription')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            // Suspending or cancelling a line should say why; the reason ends
            // up on the service status log and on the customer's history.
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function targetStatus(): SubscriptionStatus
    {
        return SubscriptionStatus::from($this->validated()['status']);
    }
}
