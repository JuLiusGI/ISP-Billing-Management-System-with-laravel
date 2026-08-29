<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesInternetPlanInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInternetPlanRequest extends FormRequest
{
    use HandlesInternetPlanInput;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('plan')) ?? false;
    }
}
