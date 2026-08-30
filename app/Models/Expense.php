<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'expense_reference',
        'expense_category_id',
        'description',
        'amount',
        'expense_date',
        'payment_method',
        'vendor',
        'notes',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
        ];
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param  Builder<Expense>  $query */
    public function scopeIncurredBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): void
    {
        $query->whereBetween('expense_date', [$from, $to]);
    }

    // -----------------------------------------------------------------
    // Audit trail
    // -----------------------------------------------------------------

    protected function auditModule(): string
    {
        return 'Expenses';
    }

    protected function auditLabel(): string
    {
        return $this->expense_reference;
    }
}
