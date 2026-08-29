<?php

namespace App\Http\Controllers;

use App\Models\BillingCycle;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingCycleController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function index(): View
    {
        $cycles = BillingCycle::query()
            ->withCount('invoices')
            ->withSum('invoices as invoiced_total', 'total_amount')
            ->withSum('invoices as outstanding_total', 'balance_due')
            ->orderByDesc('period_start')
            ->paginate(12);

        return view('billing.index', [
            'cycles' => $cycles,
            // Offered as the next cycle to open; already-open months are fine
            // to reopen, since cycleFor is find-or-create.
            'suggestedMonth' => now()->startOfMonth(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // A month picker posts YYYY-MM.
            'month' => ['required', 'date_format:Y-m', Rule::notIn([''])],
        ], [
            'month.date_format' => 'Choose a month to bill.',
        ]);

        $cycle = $this->billing->cycleFor(
            Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth(),
            $request->user(),
        );

        return redirect()
            ->route('billing.show', $cycle)
            ->with('success', "Billing cycle for {$cycle->name} is open.");
    }

    public function show(BillingCycle $cycle): View
    {
        $cycle->load('generatedBy');

        return view('billing.show', [
            'cycle' => $cycle,
            'invoices' => $cycle->invoices()
                ->with(['customer', 'subscription'])
                ->orderBy('invoice_number')
                ->paginate(20),
            // Shown before generating so the operator knows what will happen.
            'billableCount' => $this->billing->billableSubscriptions($cycle)->count(),
            'invoicedTotal' => $cycle->invoices()->sum('total_amount'),
            'outstandingTotal' => $cycle->invoices()->sum('balance_due'),
        ]);
    }

    /**
     * Runs the generator. Safe to press twice: subscriptions already invoiced
     * for the period are skipped rather than duplicated.
     */
    public function generate(Request $request, BillingCycle $cycle): RedirectResponse
    {
        $summary = $this->billing->generate($cycle, $request->user());

        $message = "{$summary['created']} invoice(s) created, {$summary['skipped']} skipped.";

        if ($summary['failed'] > 0) {
            return back()
                ->with('error', "{$message} {$summary['failed']} failed: ".implode('; ', $summary['errors']));
        }

        return back()->with('success', $message);
    }

    public function markOverdue(): RedirectResponse
    {
        $count = $this->billing->markOverdueInvoices();

        return back()->with('success', $count === 0
            ? 'No invoices needed to be marked overdue.'
            : "{$count} invoice(s) marked overdue.");
    }
}
