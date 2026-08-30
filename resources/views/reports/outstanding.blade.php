@extends('layouts.app')

@section('title', 'Outstanding report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active" aria-current="page">Outstanding</li>
@endsection

@section('content')
    <x-report-shell title="Outstanding Report"
                    description="Receivables as they stand today, aged by how long they have been owed. Ageing describes the present, so this report is not date-filtered.">

        <x-report-period :action="route('reports.outstanding')" :dated="false" />

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <x-stat label="Total receivable" :value="$report['total']" money accent="danger" />
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">Ageing</div>
                    <table class="table table-app table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Age</th><th class="text-end">Invoices</th><th class="text-end">Balance</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($report['buckets'] as $label => $bucket)
                                <tr>
                                    <td class="small">{{ $label }}</td>
                                    <td class="text-end small">{{ number_format($bucket['count']) }}</td>
                                    <td class="text-end small">&#8369;{{ number_format((float) $bucket['total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-12 col-lg-7">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">
                        Customers needing attention
                    </div>
                    @if ($report['topDebtors']->isEmpty())
                        <div class="empty-state">
                            <i class="bi bi-emoji-smile"></i>
                            <p class="mb-0 mt-2">Nothing outstanding.</p>
                        </div>
                    @else
                        <table class="table table-app table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Customer</th><th class="text-end">Invoices</th><th class="text-end">Balance</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($report['topDebtors'] as $row)
                                    <tr>
                                        <td class="small">
                                            <a href="{{ route('customers.show', $row->customer_id) }}"
                                               class="text-decoration-none">
                                                {{ $row->first_name }} {{ $row->last_name }}
                                            </a>
                                            <div class="text-secondary"><code>{{ $row->account_number }}</code></div>
                                        </td>
                                        <td class="text-end small">{{ number_format($row->invoices) }}</td>
                                        <td class="text-end fw-medium">
                                            &#8369;{{ number_format((float) $row->balance, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </x-report-shell>
@endsection
