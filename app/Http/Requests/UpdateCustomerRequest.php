<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesCustomerInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    use HandlesCustomerInput;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('customer')) ?? false;
    }
}
