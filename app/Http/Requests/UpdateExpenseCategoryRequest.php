<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('category')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('expense_categories', 'name')->ignore($this->route('category')->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            // Retiring a category hides it from new expenses without touching
            // the ones already filed under it.
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'A category with that name already exists.',
        ];
    }
}
