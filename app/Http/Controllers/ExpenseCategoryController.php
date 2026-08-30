<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        return view('expense-categories.index', [
            'categories' => ExpenseCategory::withCount('expenses')
                ->withSum('expenses', 'amount')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $category = ExpenseCategory::create($request->validated() + ['is_active' => true]);

        return redirect()
            ->route('expense-categories.index')
            ->with('success', "The {$category->name} category has been added.");
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()
            ->route('expense-categories.index')
            ->with('success', "The {$category->name} category has been updated.");
    }

    /**
     * Only an unused category can go. One that has expenses filed under it is
     * retired instead, so historical records keep a meaningful label.
     */
    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $name = $category->name;
        $category->delete();

        return redirect()
            ->route('expense-categories.index')
            ->with('success', "The {$name} category has been deleted.");
    }
}
