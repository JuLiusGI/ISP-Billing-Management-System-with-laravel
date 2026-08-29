@extends('layouts.app')

@section('title', 'Receipts')
@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Receipts</li>
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h5 mb-0 text-navy">Receipts</h2>
            <p class="small text-secondary mb-0">{{ number_format($receipts->total()) }} issued</p>
        </div>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('receipts.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-lg-6">
                    <label for="search" class="form-label small">Search</label>
                    <input type="search" name="search" id="search" class="form-control form-control-sm"
                           value="{{ request('search') }}"
                           placeholder="Receipt no., payment ref., account no. or name">
                </div>

                <div class="col-6 col-lg-2">
                    <label for="from" class="form-label small">Issued from</label>
                    <input type="date" name="from" id="from" class="form-control form-control-sm"
                           value="{{ request('from') }}">
                </div>

                <div class="col-6 col-lg-2">
                    <label for="to" class="form-label small">Issued to</label>
                    <input type="date" name="to" id="to" class="form-control form-control-sm"
                           value="{{ request('to') }}">
                </div>

                <div class="col-12 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="{{ route('receipts.index') }}" class="btn btn-sm btn-light border">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0">
        @if ($receipts->isEmpty())
            <div class="empty-state">
                <i class="bi bi-receipt-cutoff"></i>
                <p class="mb-1 mt-2">No receipts have been issued yet.</p>
                @can('payments.view')
                    <a href="{{ route('payments.index') }}" class="small">
                        Issue one from a recorded payment
                    </a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-app table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Receipt</th>
                            <th>Customer</th>
                            <th>Payment</th>
                            <th>Issued</th>
                            <th class="text-end">Amount</th>
                            <th>Issued by</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($receipts as $receipt)
                            <tr class="{{ $receipt->payment->isReversed() ? 'opacity-75' : '' }}">
                                <td>
                                    <code class="small">{{ $receipt->receipt_number }}</code>
                                    @if ($receipt->payment->isReversed())
                                        <span class="badge text-bg-danger">Void</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('customers.show', $receipt->payment->customer) }}"
                                       class="text-decoration-none">
                                        {{ $receipt->payment->customer->full_name }}
                                    </a>
                                    <div class="small text-secondary">
                                        {{ $receipt->payment->customer->account_number }}
                                    </div>
                                </td>
                                <td>
                                    <a href="{{ route('payments.show', $receipt->payment) }}"
                                       class="text-decoration-none">
                                        <code class="small">{{ $receipt->payment->payment_reference }}</code>
                                    </a>
                                </td>
                                <td class="small">{{ $receipt->issued_at->format('d M Y') }}</td>
                                <td class="text-end small fw-medium">
                                    &#8369;{{ number_format((float) $receipt->payment->amount, 2) }}
                                </td>
                                <td class="small text-secondary">{{ $receipt->issuedBy?->full_name ?? '—' }}</td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="{{ route('receipts.show', $receipt) }}"
                                           class="btn btn-sm btn-light border" aria-label="View receipt">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('receipts.print', $receipt) }}" target="_blank"
                                           class="btn btn-sm btn-light border" aria-label="Print receipt">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($receipts->hasPages())
                <div class="card-footer bg-white border-top">
                    {{ $receipts->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
@endsection
