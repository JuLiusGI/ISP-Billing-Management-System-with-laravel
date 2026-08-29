<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesCustomerInput;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    use HandlesCustomerInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }
}
