@extends('layouts.app')

@section('title', 'Payments')
@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Payments</li>
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h5 mb-0 text-navy">Payments</h2>
            <p class="small text-secondary mb-0">{{ number_format($payments->total()) }} matching</p>
        </div>

        @can('create', App\Models\Payment::class)
            <a href="{{ route('payments.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-cash-coin me-1"></i> Record payment
            </a>
        @endcan
    </div>

    <div class="card border-0 mb-3">
        <div class="card-body">
            <div class="text-secondary small">Received (filtered, completed only)</div>
            <div class="fs-5 fw-bold text-success">&#8369;{{ number_format((float) $receivedTotal, 2) }}</div>
        </div>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('payments.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-lg-4">
                    <label for="search" class="form-label small">Search</label>
                    <input type="search" name="search" id="search" class="form-control form-control-sm"
                           value="{{ request('search') }}"
                           placeholder="Payment ref., external ref., account no. or name">
                </div>

                <div class="col-6 col-lg-2">
                    <label for="method" class="form-label small">Method</label>
                    <select name="method" id="method" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($methods as $method)
                            <option value="{{ $method->value }}" @selected(request('method') === $method->value)>
                                {{ $method->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label for="status" class="form-label small">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label for="from" class="form-label small">From</label>
                    <input type="date" name="from" id="from" class="form-control form-control-sm"
                           value="{{ request('from') }}">
                </div>

                <div class="col-6 col-lg-2">
                    <label for="to" class="form-label small">To</label>
                    <input type="date" name="to" id="to" class="form-control form-control-sm"
                           value="{{ request('to') }}">
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="{{ route('payments.index') }}" class="btn btn-sm btn-light border">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0">
        @if ($payments->isEmpty())
            <div class="empty-state">
                <i class="bi bi-cash-coin"></i>
                <p class="mb-1 mt-2">No payments match these filters.</p>
                <a href="{{ route('payments.index') }}" class="small">Clear filters</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-app table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Unapplied</th>
                            <th>Status</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr class="{{ $payment->isReversed() ? 'opacity-75' : '' }}">
                                <td>
                                    <code class="small">{{ $payment->payment_reference }}</code>
                                    @if ($payment->reference_number)
                                        <div class="small text-secondary">{{ $payment->reference_number }}</div>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('customers.show', $payment->customer) }}"
                                       class="text-decoration-none">{{ $payment->customer->full_name }}</a>
                                    <div class="small text-secondary">{{ $payment->customer->account_number }}</div>
                                </td>
                                <td class="small">{{ $payment->payment_date->format('d M Y') }}</td>
                                <td class="small">{{ $payment->payment_method->label() }}</td>
                                <td class="text-end small fw-medium">
                                    &#8369;{{ number_format((float) $payment->amount, 2) }}
                                </td>
                                <td class="text-end small">
                                    @if (! $payment->isFullyAllocated() && ! $payment->isReversed())
                                        <span class="badge text-bg-warning">
                                            &#8369;{{ number_format((float) $payment->unallocatedAmount(), 2) }}
                                        </span>
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $payment->status->badgeClass() }}">
                                        {{ $payment->status->label() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('payments.show', $payment) }}"
                                       class="btn btn-sm btn-light border" aria-label="View payment">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="card-footer bg-white border-top">
                    {{ $payments->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
@endsection
