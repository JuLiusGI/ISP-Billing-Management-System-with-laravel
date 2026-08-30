@extends('layouts.app')

@section('title', 'Billing report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active" aria-current="page">Billing</li>
@endsection

@section('content')
    <x-report-shell title="Billing Report"
                    description="Invoices issued in the period and where they ended up. Cancelled and void invoices are counted but excluded from the billed totals."
                    :from="$from" :to="$to">

        <x-report-period :action="route('reports.billing')" :from="$from" :to="$to" />

        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <x-stat label="Invoices issued" :value="$report['count']" />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat label="Total billed" :value="$report['invoiced']" money />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat label="Collected" :value="$report['paid']" money accent="success" />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat label="Still outstanding" :value="$report['outstanding']" money accent="danger" />
            </div>
        </div>

        <div class="card border-0">
            <div class="card-header bg-white border-bottom fw-semibold text-navy">By status</div>

            @if ($report['byStatus']->isEmpty())
                <div class="empty-state">
                    <i class="bi bi-receipt"></i>
                    <p class="mb-0 mt-2">No invoices issued in this period.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-app table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Status</th>
                                <th class="text-end">Invoices</th>
                                <th class="text-end">Total billed</th>
                                <th class="text-end">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['byStatus'] as $row)
                                <tr>
                                    <td>
                                        <span class="badge {{ $row->status->badgeClass() }}">
                                            {{ $row->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-end small">{{ number_format($row->entries) }}</td>
                                    <td class="text-end small">&#8369;{{ number_format((float) $row->total, 2) }}</td>
                                    <td class="text-end small">&#8369;{{ number_format((float) $row->balance, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-report-shell>
@endsection
