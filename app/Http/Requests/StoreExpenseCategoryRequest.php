<?php

namespace App\Http\Requests;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ExpenseCategory::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        // The code is derived rather than typed, matching how the seeded
        // categories are named (UPSTREAM, OFFICE_SUPPLIES...).
        $this->merge([
            'code' => Str::upper(Str::snake(Str::ascii((string) $this->input('name')))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')],
            'code' => ['required', 'string', 'max:40', Rule::unique('expense_categories', 'code')],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'A category with that name already exists.',
            'code.unique' => 'A category with that name already exists.',
        ];
    }
}
