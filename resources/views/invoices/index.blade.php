@extends('layouts.app')

@section('title', 'Invoices')
@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Invoices</li>
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h5 mb-0 text-navy">Invoices</h2>
            <p class="small text-secondary mb-0">{{ number_format($invoices->total()) }} matching</p>
        </div>

        @can('create', App\Models\Invoice::class)
            <a href="{{ route('invoices.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Create invoice
            </a>
        @endcan
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6">
            <div class="card border-0 h-100">
                <div class="card-body">
                    <div class="text-secondary small">Invoiced (filtered)</div>
                    <div class="fs-5 fw-bold text-navy">&#8369;{{ number_format((float) $invoicedTotal, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card border-0 h-100">
                <div class="card-body">
                    <div class="text-secondary small">Outstanding (filtered)</div>
                    <div class="fs-5 fw-bold text-danger">&#8369;{{ number_format((float) $outstandingTotal, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('invoices.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-lg-3">
                    <label for="search" class="form-label small">Search</label>
                    <input type="search" name="search" id="search" class="form-control form-control-sm"
                           value="{{ request('search') }}" placeholder="Invoice no., account no. or name">
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
                    <label for="view" class="form-label small">View</label>
                    <select name="view" id="view" class="form-select form-select-sm">
                        <option value="">Everything</option>
                        <option value="outstanding" @selected(request('view') === 'outstanding')>Outstanding</option>
                        <option value="overdue" @selected(request('view') === 'overdue')>Overdue</option>
                    </select>
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

                <div class="col-12 col-lg-1 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-funnel"></i>
                    </button>
                </div>

                <div class="col-6 col-lg-2">
                    <label for="min" class="form-label small">Amount from</label>
                    <input type="number" step="0.01" min="0" name="min" id="min"
                           class="form-control form-control-sm" value="{{ request('min') }}">
                </div>

                <div class="col-6 col-lg-2">
                    <label for="max" class="form-label small">Amount to</label>
                    <input type="number" step="0.01" min="0" name="max" id="max"
                           class="form-control form-control-sm" value="{{ request('max') }}">
                </div>

                <div class="col-12 col-lg-2">
                    <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-light border w-100">Clear filters</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0">
        @if ($invoices->isEmpty())
            <div class="empty-state">
                <i class="bi bi-receipt"></i>
                <p class="mb-1 mt-2">No invoices match these filters.</p>
                <a href="{{ route('invoices.index') }}" class="small">Clear filters</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-app table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th>Issued</th>
                            <th>Due</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Balance</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td><code class="small">{{ $invoice->invoice_number }}</code></td>
                                <td>
                                    <a href="{{ route('customers.show', $invoice->customer) }}"
                                       class="text-decoration-none">{{ $invoice->customer->full_name }}</a>
                                    <div class="small text-secondary">{{ $invoice->customer->account_number }}</div>
                                </td>
                                <td class="small">{{ $invoice->invoice_date->format('d M Y') }}</td>
                                <td class="small">
                                    {{ $invoice->due_date->format('d M Y') }}
                                    @if ($invoice->isOverdue())
                                        <div class="text-danger">{{ $invoice->daysOverdue() }} day(s) late</div>
                                    @endif
                                </td>
                                <td class="text-end small">&#8369;{{ number_format((float) $invoice->total_amount, 2) }}</td>
                                <td class="text-end small fw-medium">
                                    &#8369;{{ number_format((float) $invoice->balance_due, 2) }}
                                </td>
                                <td>
                                    <span class="badge {{ $invoice->status->badgeClass() }}">
                                        {{ $invoice->status->label() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="{{ route('invoices.show', $invoice) }}"
                                           class="btn btn-sm btn-light border" aria-label="View invoice">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('invoices.print', $invoice) }}" target="_blank"
                                           class="btn btn-sm btn-light border" aria-label="Print invoice">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($invoices->hasPages())
                <div class="card-footer bg-white border-top">
                    {{ $invoices->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
@endsection
