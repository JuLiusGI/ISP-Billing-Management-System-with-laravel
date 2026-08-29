@extends('layouts.app')

@section('title', $payment->payment_reference)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('payments.index') }}">Payments</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $payment->payment_reference }}</li>
@endsection

@section('content')
    @if ($payment->isReversed())
        <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-arrow-counterclockwise mt-1"></i>
            <div class="small">
                <strong>Reversed</strong>
                on {{ $payment->reversed_at?->format('d M Y, g:i A') }}
                by {{ $payment->reversedBy?->full_name ?? 'system' }}.
                @if ($payment->reversal_reason)
                    Reason: {{ $payment->reversal_reason }}
                @endif
                <div>
                    This payment no longer counts toward any invoice balance. The record is kept
                    for the audit trail.
                </div>
            </div>
        </div>
    @endif

    <div class="card border-0 mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center">
            <div class="flex-grow-1">
                <h2 class="h5 mb-1 text-navy">
                    &#8369;{{ number_format((float) $payment->amount, 2) }}
                    <span class="badge {{ $payment->status->badgeClass() }} align-middle">
                        {{ $payment->status->label() }}
                    </span>
                </h2>
                <div class="small text-secondary">
                    <code>{{ $payment->payment_reference }}</code>
                    &middot;
                    <a href="{{ route('customers.show', $payment->customer) }}" class="text-decoration-none">
                        {{ $payment->customer->full_name }} ({{ $payment->customer->account_number }})
                    </a>
                    &middot; {{ $payment->payment_date->format('d M Y') }}
                    &middot; {{ $payment->payment_method->label() }}
                </div>
            </div>

            <div class="d-flex gap-2">
                @if ($payment->receipt)
                    <a href="{{ route('receipts.show', $payment->receipt) }}" class="btn btn-sm btn-light border">
                        <i class="bi bi-receipt-cutoff me-1"></i> Receipt {{ $payment->receipt->receipt_number }}
                    </a>
                    <a href="{{ route('receipts.print', $payment->receipt) }}" target="_blank"
                       class="btn btn-sm btn-primary">
                        <i class="bi bi-printer me-1"></i> Print
                    </a>
                @elseif (auth()->user()->can('issueReceipt', $payment))
                    <form method="POST" action="{{ route('payments.receipt', $payment) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-receipt-cutoff me-1"></i> Issue receipt
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card border-0 mb-3">
                <div class="card-header bg-white border-bottom fw-semibold text-navy">Applied to</div>
                <div class="card-body">
                    @forelse ($payment->allocations as $allocation)
                        <div class="d-flex justify-content-between align-items-center {{ $loop->last ? '' : 'border-bottom' }} py-2 small">
                            <div>
                                <a href="{{ route('invoices.show', $allocation->invoice) }}"
                                   class="text-decoration-none">
                                    <code>{{ $allocation->invoice->invoice_number }}</code>
                                </a>
                                <span class="badge {{ $allocation->invoice->status->badgeClass() }}">
                                    {{ $allocation->invoice->status->label() }}
                                </span>
                                <div class="text-secondary">
                                    Invoice total &#8369;{{ number_format((float) $allocation->invoice->total_amount, 2) }}
                                    &middot; balance now
                                    &#8369;{{ number_format((float) $allocation->invoice->balance_due, 2) }}
                                </div>
                            </div>
                            <div class="fw-medium">&#8369;{{ number_format((float) $allocation->amount, 2) }}</div>
                        </div>
                    @empty
                        <p class="small text-secondary mb-0">
                            None of this payment has been applied to an invoice yet.
                        </p>
                    @endforelse
                </div>
            </div>

            @can('allocate', $payment)
                @if ($outstanding->isNotEmpty())
                    <div class="card border-0">
                        <div class="card-header bg-white border-bottom fw-semibold text-navy">
                            Apply the remaining
                            &#8369;{{ number_format((float) $payment->unallocatedAmount(), 2) }}
                        </div>
                        <form method="POST" action="{{ route('payments.allocate', $payment) }}">
                            @csrf
                            <div class="table-responsive">
                                <table class="table table-app table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Invoice</th>
                                            <th>Due</th>
                                            <th class="text-end">Balance</th>
                                            <th style="width:10rem;">Apply</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($outstanding as $invoice)
                                            <tr>
                                                <td><code class="small">{{ $invoice->invoice_number }}</code></td>
                                                <td class="small">{{ $invoice->due_date->format('d M Y') }}</td>
                                                <td class="text-end small">
                                                    &#8369;{{ number_format((float) $invoice->balance_due, 2) }}
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0"
                                                           max="{{ $invoice->balance_due }}"
                                                           name="allocations[{{ $invoice->id }}]"
                                                           class="form-control form-control-sm" placeholder="0.00">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-white border-top">
                                <button type="submit" class="btn btn-sm btn-primary">Apply credit</button>
                            </div>
                        </form>
                    </div>
                @endif
            @endcan
        </div>

        <div class="col-12 col-lg-5">
            <div class="card border-0 mb-3">
                <div class="card-header bg-white border-bottom fw-semibold text-navy">Summary</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-7 text-secondary fw-normal">Amount received</dt>
                        <dd class="col-5 text-end">&#8369;{{ number_format((float) $payment->amount, 2) }}</dd>

                        <dt class="col-7 text-secondary fw-normal">Applied to invoices</dt>
                        <dd class="col-5 text-end">&#8369;{{ number_format((float) $payment->allocated_amount, 2) }}</dd>

                        <dt class="col-7 fw-semibold border-top pt-2">Unapplied credit</dt>
                        <dd class="col-5 text-end fw-semibold border-top pt-2 mb-0">
                            &#8369;{{ number_format((float) $payment->unallocatedAmount(), 2) }}
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card border-0 mb-3">
                <div class="card-header bg-white border-bottom fw-semibold text-navy">Details</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-secondary fw-normal">Method</dt>
                        <dd class="col-7">{{ $payment->payment_method->label() }}</dd>

                        <dt class="col-5 text-secondary fw-normal">External ref.</dt>
                        <dd class="col-7">{{ $payment->reference_number ?: '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Received by</dt>
                        <dd class="col-7">{{ $payment->receivedBy?->full_name ?? 'System' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Recorded</dt>
                        <dd class="col-7 mb-0">{{ $payment->created_at->format('d M Y, g:i A') }}</dd>
                    </dl>

                    @if ($payment->notes)
                        <hr>
                        <div class="small" style="white-space: pre-line;">{{ $payment->notes }}</div>
                    @endif
                </div>
            </div>

            @can('reverse', $payment)
                <div class="card border-0">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">Reverse payment</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('payments.reverse', $payment) }}"
                              data-confirm="Reverse {{ $payment->payment_reference }}? The invoice balances it covered will be restored.">
                            @csrf
                            @method('PATCH')

                            <label for="reason" class="form-label small">Reason <span class="text-danger">*</span></label>
                            <input type="text" name="reason" id="reason" maxlength="255" required
                                   class="form-control form-control-sm @error('reason') is-invalid @enderror"
                                   value="{{ old('reason') }}" placeholder="Bounced cheque, entered in error…">
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror

                            <button type="submit" class="btn btn-outline-danger btn-sm w-100 mt-2">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reverse this payment
                            </button>
                        </form>
                        <p class="form-text mb-0 mt-2">
                            The record and its allocations are kept; the reversal is what stops the money counting.
                        </p>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
