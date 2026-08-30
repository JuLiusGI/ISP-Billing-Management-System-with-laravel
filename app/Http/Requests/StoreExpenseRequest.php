<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesExpenseInput;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    use HandlesExpenseInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Expense::class) ?? false;
    }
}
