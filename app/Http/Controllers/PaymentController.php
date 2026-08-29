<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\PaymentService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::query()
            ->with(['customer', 'receivedBy'])
            ->when($request->filled('search'), function (Builder $q) use ($request): void {
                $term = '%'.$request->string('search').'%';

                $q->where(function (Builder $inner) use ($term): void {
                    $inner->where('payment_reference', 'like', $term)
                        ->orWhere('reference_number', 'like', $term)
                        ->orWhereHas('customer', fn (Builder $c) => $c
                            ->where('account_number', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term));
                });
            })
            ->when($request->filled('method'), fn (Builder $q) => $q->where('payment_method', $request->string('method')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('payment_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('payment_date', '<=', $request->date('to')))
            ->latest('payment_date')
            ->latest('id');

        // Only completed payments are money the ISP actually holds, so the
        // headline figure excludes reversed rows even when they are listed.
        $received = (clone $query)->where('status', PaymentStatus::Completed)
            ->reorder()
            ->sum('amount');

        return view('payments.index', [
            'payments' => $query->paginate(20)->withQueryString(),
            'methods' => PaymentMethod::cases(),
            'statuses' => PaymentStatus::cases(),
            'receivedTotal' => $received,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Payment::class);

        // The customer is chosen first, because their outstanding invoices are
        // what the allocation grid is built from. Selecting one reloads here.
        $customer = $request->filled('customer')
            ? Customer::find($request->integer('customer'))
            : null;

        return view('payments.create', [
            'customers' => Customer::orderBy('last_name')->orderBy('first_name')->get(),
            'customer' => $customer,
            'outstanding' => $customer ? $this->payments->outstandingInvoicesFor($customer) : collect(),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $customer = Customer::findOrFail($request->validated('customer_id'));

        try {
            $payment = $this->payments->record(
                $customer,
                $request->safe()->only(['payment_date', 'amount', 'payment_method', 'reference_number', 'notes']),
                $request->allocations(),
                $request->user(),
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $credit = $payment->unallocatedAmount();
        $note = bccomp($credit, '0', 2) === 1
            ? " ₱{$credit} is held as unapplied credit."
            : '';

        return redirect()
            ->route('payments.show', $payment)
            ->with('success', "Payment {$payment->payment_reference} recorded.{$note}");
    }

    public function show(Payment $payment): View
    {
        $this->authorize('view', $payment);

        $payment->load(['customer', 'receivedBy', 'reversedBy', 'allocations.invoice', 'receipt']);

        return view('payments.show', [
            'payment' => $payment,
            // Offered only while there is credit left to apply.
            'outstanding' => $payment->isFullyAllocated()
                ? collect()
                : $this->payments->outstandingInvoicesFor($payment->customer),
        ]);
    }

    /** Applies a payment's leftover credit to further invoices. */
    public function allocate(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('allocate', $payment);

        $validated = $request->validate([
            'allocations' => ['required', 'array'],
            'allocations.*' => ['numeric', 'gte:0', 'decimal:0,2'],
        ]);

        $amounts = array_filter($validated['allocations'], fn ($a) => (float) $a > 0);

        if ($amounts === []) {
            return back()->with('error', 'Enter an amount to apply to at least one invoice.');
        }

        try {
            $this->payments->allocate($payment, $amounts);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'The credit has been applied.');
    }

    public function reverse(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('reverse', $payment);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Give a reason for reversing this payment.',
        ]);

        try {
            $this->payments->reverse($payment, $validated['reason'], $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            "Payment {$payment->payment_reference} reversed. The invoice balances it covered have been restored."
        );
    }
}
