<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PlanBillingCycle;
use App\Enums\SpeedUnit;
use App\Models\InternetPlan;
use Illuminate\Validation\Rule;

/**
 * Shared by the plan store and update requests so the two cannot drift.
 */
trait HandlesInternetPlanInput
{
    protected function prepareForValidation(): void
    {
        // Plan codes are compared and printed, so normalise the case rather
        // than letting "HOME-50" and "home-50" both exist.
        if ($this->filled('plan_code')) {
            $this->merge(['plan_code' => strtoupper(trim((string) $this->input('plan_code')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'plan_code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('internet_plans', 'plan_code')
                    ->ignore($plan instanceof InternetPlan ? $plan->id : null)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:120'],

            'download_speed' => ['required', 'integer', 'min:1', 'max:1000000'],
            'upload_speed' => ['required', 'integer', 'min:1', 'max:1000000'],
            'speed_unit' => ['required', Rule::enum(SpeedUnit::class)],

            // Money is validated as a decimal with at most two places, so a
            // stray third decimal is rejected rather than silently rounded.
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'installation_fee' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'activation_fee' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],

            'billing_cycle' => ['required', Rule::enum(PlanBillingCycle::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'plan_code.regex' => 'The plan code may only use letters, numbers and hyphens.',
            'plan_code.unique' => 'Another plan already uses that code.',
            'monthly_price.decimal' => 'The monthly price may have at most two decimal places.',
            'installation_fee.decimal' => 'The installation fee may have at most two decimal places.',
            'activation_fee.decimal' => 'The activation fee may have at most two decimal places.',
        ];
    }
}
