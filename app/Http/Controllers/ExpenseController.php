<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $filtered = $this->filtered($request);

        // Each consumer gets its own clone, so the totals describe the whole
        // filtered set rather than just the page being looked at.
        return view('expenses.index', [
            'expenses' => (clone $filtered)
                ->with(['category', 'createdBy'])
                ->orderByDesc('expense_date')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'total' => (string) (clone $filtered)->sum('amount'),
            'byCategory' => $this->totalsByCategory(clone $filtered),
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Expense::class);

        return view('expenses.create', $this->formOptions());
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = $this->expenses->record($request->validated(), $request->user());

        return redirect()
            ->route('expenses.show', $expense)
            ->with('success', "Expense {$expense->expense_reference} has been recorded.");
    }

    public function show(Expense $expense): View
    {
        $this->authorize('view', $expense);

        return view('expenses.show', [
            'expense' => $expense->load('category', 'createdBy'),
        ]);
    }

    public function edit(Expense $expense): View
    {
        $this->authorize('update', $expense);

        return view('expenses.edit', $this->formOptions($expense) + ['expense' => $expense]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->expenses->update($expense, $request->validated());

        return redirect()
            ->route('expenses.show', $expense)
            ->with('success', "Expense {$expense->expense_reference} has been updated.");
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $this->expenses->archive($expense);

        return redirect()
            ->route('expenses.index')
            ->with('success', "Expense {$expense->expense_reference} has been archived.");
    }

    public function restore(int $expense): RedirectResponse
    {
        $archived = Expense::onlyTrashed()->findOrFail($expense);

        $this->authorize('restore', $archived);

        $this->expenses->restore($archived);

        return redirect()
            ->route('expenses.show', $archived)
            ->with('success', "Expense {$archived->expense_reference} has been restored.");
    }

    /**
     * The shared filter, applied identically to the listing and the totals so
     * the figures always describe the rows on screen.
     *
     * @return Builder<Expense>
     */
    private function filtered(Request $request): Builder
    {
        return Expense::query()
            ->when($request->boolean('archived'), fn (Builder $q) => $q->onlyTrashed())
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search').'%';

                $query->where(function (Builder $q) use ($term): void {
                    $q->where('expense_reference', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('vendor', 'like', $term);
                });
            })
            ->when($request->filled('category'), fn (Builder $q) => $q->where('expense_category_id', $request->integer('category')))
            ->when($request->filled('method'), fn (Builder $q) => $q->where('payment_method', $request->string('method')))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('expense_date', '>=', $request->string('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('expense_date', '<=', $request->string('to')));
    }

    /**
     * Spend per category for the filtered range, largest first.
     *
     * @param  Builder<Expense>  $query
     * @return Collection<int, object>
     */
    private function totalsByCategory(Builder $query)
    {
        // Stays on the Eloquent builder rather than dropping to the query
        // builder: the soft-delete scope is applied at the Eloquent level, and
        // going underneath it would quietly count archived expenses.
        return $query
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total')
            ->get([
                'expense_categories.name as name',
                DB::raw('SUM(expenses.amount) as total'),
                DB::raw('COUNT(*) as entries'),
            ]);
    }

    /** @return array<string, mixed> */
    private function formOptions(?Expense $expense = null): array
    {
        // The category already on the record stays selectable even once it has
        // been retired, so an old expense can still be edited.
        $categories = ExpenseCategory::query()
            ->where('is_active', true)
            ->when($expense, fn (Builder $q) => $q->orWhere('id', $expense->expense_category_id))
            ->orderBy('name')
            ->get();

        return [
            'categories' => $categories,
            'methods' => PaymentMethod::cases(),
        ];
    }
}
