<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesInvoiceInput;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    use HandlesInvoiceInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Invoice::class) ?? false;
    }
}
