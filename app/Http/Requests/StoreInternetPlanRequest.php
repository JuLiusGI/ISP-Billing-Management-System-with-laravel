<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesInternetPlanInput;
use App\Models\InternetPlan;
use Illuminate\Foundation\Http\FormRequest;

class StoreInternetPlanRequest extends FormRequest
{
    use HandlesInternetPlanInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', InternetPlan::class) ?? false;
    }
}
