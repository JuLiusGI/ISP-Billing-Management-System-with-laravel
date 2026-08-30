<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Recording operating costs.
 *
 * Simpler than the billing services: an expense has no allocations and no
 * balance. What it does share is the reference-number problem, so the same
 * generate-and-retry approach is used rather than a second invented one.
 */
class ExpenseService
{
    private const REFERENCE_ATTEMPTS = 5;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes, ?User $actor = null): Expense
    {
        $attributes['created_by'] = $actor?->id;

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): Expense => Expense::create(
                    $attributes + ['expense_reference' => $this->nextReference()]
                ));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::REFERENCE_ATTEMPTS || ! str_contains($e->getMessage(), 'expense_reference')) {
                    throw $e;
                }
                // Another request took the number; the next attempt reads a
                // fresh maximum.
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Expense $expense, array $attributes): Expense
    {
        // The reference identifies the record in the books and is never edited.
        unset($attributes['expense_reference'], $attributes['created_by']);

        $expense->update($attributes);

        return $expense->refresh();
    }

    /**
     * Archives an expense.
     *
     * Soft delete rather than removal: an expense that has already been
     * counted in a period's figures must remain recoverable and auditable.
     */
    public function archive(Expense $expense): void
    {
        $expense->delete();
    }

    public function restore(Expense $expense): void
    {
        $expense->restore();
    }

    /**
     * Numbers run EXP-YYYY-NNNNNN. Derived from the current maximum id, so
     * concurrent requests can collide; the unique index is the guarantee and
     * record() retries.
     */
    public function nextReference(): string
    {
        $sequence = (Expense::withTrashed()->max('id') ?? 0) + 1;

        return sprintf('EXP-%s-%06d', date('Y'), $sequence);
    }
}
