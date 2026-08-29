<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\SettingsService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        $query = Invoice::query()
            ->with(['customer', 'subscription'])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search').'%';

                $query->where(function (Builder $q) use ($term): void {
                    $q->where('invoice_number', 'like', $term)
                        ->orWhereHas('customer', fn (Builder $c) => $c
                            ->where('account_number', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term));
                });
            })
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            // "outstanding" and "overdue" are views over several statuses.
            ->when($request->string('view')->toString() === 'outstanding', fn (Builder $q) => $q->outstanding())
            ->when($request->string('view')->toString() === 'overdue', fn (Builder $q) => $q->overdue())
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('invoice_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('invoice_date', '<=', $request->date('to')))
            ->when($request->filled('min'), fn (Builder $q) => $q->where('total_amount', '>=', $request->float('min')))
            ->when($request->filled('max'), fn (Builder $q) => $q->where('total_amount', '<=', $request->float('max')))
            ->latest('invoice_date')
            ->latest('id');

        // Summed over the whole filtered set, before pagination narrows it.
        $totals = (clone $query)->selectRaw(
            'COALESCE(SUM(total_amount), 0) AS invoiced, COALESCE(SUM(balance_due), 0) AS outstanding'
        )->reorder()->first();

        return view('invoices.index', [
            'invoices' => $query->paginate(20)->withQueryString(),
            'statuses' => InvoiceStatus::cases(),
            'invoicedTotal' => $totals->invoiced,
            'outstandingTotal' => $totals->outstanding,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Invoice::class);

        $invoiceDate = now();

        return view('invoices.create', $this->formOptions() + [
            'selectedCustomer' => $request->filled('customer')
                ? Customer::find($request->integer('customer'))
                : null,
            'defaultInvoiceDate' => $invoiceDate->toDateString(),
            'defaultDueDate' => $this->invoices->dueDateFor($invoiceDate)->toDateString(),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $customer = Customer::findOrFail($request->validated('customer_id'));

        $invoice = $this->invoices->create(
            $customer,
            $request->lineItems(),
            $request->invoiceAttributes(),
            $request->user(),
        );

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', "Invoice {$invoice->invoice_number} has been created.");
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        return view('invoices.show', [
            'invoice' => $invoice->load([
                'customer.primaryAddress', 'subscription.internetPlan', 'items',
                'billingCycle', 'createdBy', 'cancelledBy',
                'allocations.payment',
            ]),
        ]);
    }

    /** Printer-friendly rendering, driven from the browser's print dialog. */
    public function print(Invoice $invoice): View
    {
        $this->authorize('print', $invoice);

        return view('invoices.print', [
            'invoice' => $invoice->load(['customer.primaryAddress', 'items', 'allocations.payment']),
            'company' => [
                'name' => $this->settings->string('company.name', config('app.name')),
                'address' => $this->settings->string('company.address'),
                'phone' => $this->settings->string('company.phone'),
                'email' => $this->settings->string('company.email'),
                'website' => $this->settings->string('company.website'),
            ],
        ]);
    }

    public function edit(Invoice $invoice): View
    {
        $this->authorize('update', $invoice);

        $invoice->load('items', 'customer');

        return view('invoices.edit', $this->formOptions() + [
            'invoice' => $invoice,
            // Shown on its own; discount_total on the row combines this with
            // the per-line discounts.
            'invoiceLevelDiscount' => $this->invoices->invoiceLevelDiscount($invoice),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->invoices->update($invoice, $request->lineItems(), $request->invoiceAttributes());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', "Invoice {$invoice->invoice_number} has been updated.");
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('cancel', $invoice);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Give a reason for cancelling this invoice.',
        ]);

        try {
            $this->invoices->cancel($invoice, $validated['reason'], $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', "Invoice {$invoice->invoice_number} has been cancelled.");
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'customers' => Customer::orderBy('last_name')->orderBy('first_name')->get(),
            'itemTypes' => InvoiceItemType::cases(),
        ];
    }
}
