<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        // The machine name is derived, never typed, so it stays URL and
        // comparison safe however the display name is written.
        $this->merge([
            'name' => Str::slug((string) $this->input('display_name')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'permissions.required' => 'Grant at least one ability to this role.',
            'name.unique' => 'A role with that name already exists.',
        ];
    }
}
