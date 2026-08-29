<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Receipt;
use App\Services\ReceiptService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function __construct(private readonly ReceiptService $receipts) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Receipt::class);

        $receipts = Receipt::query()
            ->with(['payment.customer', 'issuedBy'])
            ->when($request->filled('search'), function (Builder $q) use ($request): void {
                $term = '%'.$request->string('search').'%';

                $q->where('receipt_number', 'like', $term)
                    ->orWhereHas('payment', fn (Builder $p) => $p
                        ->where('payment_reference', 'like', $term)
                        ->orWhereHas('customer', fn (Builder $c) => $c
                            ->where('account_number', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)));
            })
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('issued_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('issued_at', '<=', $request->date('to')))
            ->latest('issued_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('receipts.index', ['receipts' => $receipts]);
    }

    /** Issues the receipt for a payment, then goes straight to it. */
    public function store(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('issueReceipt', $payment);

        try {
            $receipt = $this->receipts->issue($payment, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('receipts.show', $receipt)
            ->with('success', "Receipt {$receipt->receipt_number} issued.");
    }

    public function show(Receipt $receipt): View
    {
        $this->authorize('view', $receipt);

        return view('receipts.show', $this->receiptData($receipt));
    }

    /** Printer-friendly rendering, driven from the browser's print dialog. */
    public function print(Receipt $receipt): View
    {
        $this->authorize('view', $receipt);

        return view('receipts.print', $this->receiptData($receipt));
    }

    /** @return array<string, mixed> */
    private function receiptData(Receipt $receipt): array
    {
        $receipt->load([
            'issuedBy',
            'payment.customer.primaryAddress',
            'payment.receivedBy',
            'payment.allocations.invoice',
        ]);

        return [
            'receipt' => $receipt,
            'payment' => $receipt->payment,
            'company' => $this->receipts->companyDetails(),
        ];
    }
}
