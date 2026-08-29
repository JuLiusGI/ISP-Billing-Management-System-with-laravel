<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesInvoiceInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    use HandlesInvoiceInput;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('invoice')) ?? false;
    }
}
