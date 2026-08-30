@extends('layouts.app')

@section('title', 'Payment report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active" aria-current="page">Payments</li>
@endsection

@section('content')
    <x-report-shell title="Payment Report"
                    description="Every payment taken in the period. Completed only, unless a status is chosen."
                    :from="$from" :to="$to">

        <x-report-period :action="route('reports.payments')" :from="$from" :to="$to">
            <div class="col-6 col-lg-2">
                <label for="method" class="form-label small">Method</label>
                <select name="method" id="method" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($methods as $option)
                        <option value="{{ $option->value }}" @selected(request('method') === $option->value)>
                            {{ $option->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-lg-2">
                <label for="status" class="form-label small">Status</label>
                <select name="status" id="status" class="form-select form-select-sm">
                    <option value="">Completed only</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option->value }}" @selected(request('status') === $option->value)>
                            {{ $option->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
        </x-report-period>

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6">
                <x-stat label="Total" :value="$total" money accent="success" />
            </div>
            <div class="col-12 col-md-6">
                <x-stat label="Payments" :value="$count" />
            </div>
        </div>

        <div class="card border-0">
            @if ($payments->isEmpty())
                <div class="empty-state">
                    <i class="bi bi-cash-coin"></i>
                    <p class="mb-0 mt-2">No payments match this selection.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-app table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Reference</th><th>Date</th><th>Customer</th>
                                <th>Method</th><th>Received by</th><th>Status</th><th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                <tr>
                                    <td>
                                        <a href="{{ route('payments.show', $payment) }}" class="text-decoration-none">
                                            <code class="small">{{ $payment->payment_reference }}</code>
                                        </a>
                                    </td>
                                    <td class="small text-nowrap">{{ $payment->payment_date->format('d M Y') }}</td>
                                    <td class="small">
                                        {{ $payment->customer?->full_name ?? '—' }}
                                        <div class="text-secondary">
                                            <code>{{ $payment->customer?->account_number }}</code>
                                        </div>
                                    </td>
                                    <td class="small">{{ $payment->payment_method->label() }}</td>
                                    <td class="small text-secondary">{{ $payment->receivedBy?->full_name ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $payment->status->badgeClass() }}">
                                            {{ $payment->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-end fw-medium">
                                        &#8369;{{ number_format((float) $payment->amount, 2) }}
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
    </x-report-shell>
@endsection
