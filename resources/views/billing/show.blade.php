@extends('layouts.app')

@section('title', 'Billing cycle — '.$cycle->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('billing.index') }}">Billing cycles</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $cycle->name }}</li>
@endsection

@section('content')
    <div class="card border-0 mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center">
            <div class="flex-grow-1">
                <h2 class="h5 mb-1 text-navy">
                    {{ $cycle->name }}
                    <span class="badge {{ $cycle->status->badgeClass() }} align-middle">
                        {{ $cycle->status->label() }}
                    </span>
                </h2>
                <div class="small text-secondary">
                    Period {{ $cycle->period_start->format('d M Y') }} –
                    {{ $cycle->period_end->format('d M Y') }}
                    &middot; due {{ $cycle->due_date->format('d M Y') }}
                    @if ($cycle->generated_at)
                        &middot; generated {{ $cycle->generated_at->format('d M Y, g:i A') }}
                        by {{ $cycle->generatedBy?->full_name ?? 'system' }}
                    @endif
                </div>
            </div>

            @can('billing.generate')
                <form method="POST" action="{{ route('billing.generate', $cycle) }}"
                      data-confirm="Generate invoices for {{ $cycle->name }}? Subscriptions already invoiced for this period are skipped.">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-receipt me-1"></i> Generate invoices
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['label' => 'Billable subscriptions', 'value' => number_format($billableCount), 'money' => false],
            ['label' => 'Invoices issued', 'value' => number_format($invoices->total()), 'money' => false],
            ['label' => 'Total invoiced', 'value' => $invoicedTotal, 'money' => true],
            ['label' => 'Still outstanding', 'value' => $outstandingTotal, 'money' => true],
        ] as $stat)
            <div class="col-6 col-xl-3">
                <div class="card border-0 h-100">
                    <div class="card-body">
                        <div class="text-secondary small">{{ $stat['label'] }}</div>
                        <div class="fs-5 fw-bold text-navy">
                            @if ($stat['money'])
                                &#8369;{{ number_format((float) $stat['value'], 2) }}
                            @else
                                {{ $stat['value'] }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0">
        <div class="card-header bg-white border-bottom fw-semibold text-navy">Invoices in this cycle</div>

        @if ($invoices->isEmpty())
            <div class="empty-state">
                <i class="bi bi-receipt"></i>
                <p class="mb-0 mt-2">
                    No invoices generated yet.
                    @can('billing.generate')
                        {{ $billableCount }} subscription(s) are ready to bill.
                    @endcan
                </p>
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
                                <td class="small">{{ $invoice->due_date->format('d M Y') }}</td>
                                <td class="text-end small">&#8369;{{ number_format((float) $invoice->total_amount, 2) }}</td>
                                <td class="text-end small">&#8369;{{ number_format((float) $invoice->balance_due, 2) }}</td>
                                <td>
                                    <span class="badge {{ $invoice->status->badgeClass() }}">
                                        {{ $invoice->status->label() }}
                                    </span>
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
