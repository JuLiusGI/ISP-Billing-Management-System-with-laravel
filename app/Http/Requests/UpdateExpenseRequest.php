<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesExpenseInput;
use App\Models\ExpenseCategory;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    use HandlesExpenseInput;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('expense')) ?? false;
    }

    /**
     * Editing an expense filed under a since-retired category must not be
     * blocked by that category no longer being selectable, so the one already
     * on the record stays valid while every other choice must be active.
     *
     * @return array<int, mixed>
     */
    protected function categoryRule(): array
    {
        $current = (int) $this->route('expense')->expense_category_id;

        return [
            'required', 'integer',
            Rule::exists('expense_categories', 'id'),
            function (string $attribute, mixed $value, Closure $fail) use ($current): void {
                if ((int) $value === $current) {
                    return;
                }

                $active = ExpenseCategory::whereKey($value)->where('is_active', true)->exists();

                if (! $active) {
                    $fail('Choose an active expense category.');
                }
            },
        ];
    }
}
