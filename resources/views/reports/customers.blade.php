@extends('layouts.app')

@section('title', 'Customer report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active" aria-current="page">Customers</li>
@endsection

@section('content')
    <x-report-shell title="Customer Report"
                    description="Standing totals for the whole base; growth counts sign-ups within the chosen range."
                    :from="$from" :to="$to">

        <x-report-period :action="route('reports.customers')" :from="$from" :to="$to" />

        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-4">
                <x-stat label="Total customers" :value="$report['total']" />
            </div>
            <div class="col-6 col-lg-4">
                <x-stat label="Active" :value="$report['activeShare']" accent="success"
                        :hint="$report['total'] > 0
                            ? number_format(($report['activeShare'] / $report['total']) * 100, 1).'% of the base'
                            : null" />
            </div>
            <div class="col-12 col-lg-4">
                <x-stat label="New in this period" :value="$report['newInPeriod']" accent="primary" />
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">By status</div>
                    <table class="table table-app table-hover mb-0">
                        <thead class="table-light"><tr><th>Status</th><th class="text-end">Customers</th></tr></thead>
                        <tbody>
                            @foreach ($report['byStatus'] as $row)
                                <tr>
                                    <td>
                                        <span class="badge {{ $row->status->badgeClass() }}">
                                            {{ $row->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-end small">{{ number_format($row->entries) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">By type</div>
                    <table class="table table-app table-hover mb-0">
                        <thead class="table-light"><tr><th>Type</th><th class="text-end">Customers</th></tr></thead>
                        <tbody>
                            @foreach ($report['byType'] as $row)
                                <tr>
                                    <td class="small">{{ $row->customer_type->label() }}</td>
                                    <td class="text-end small">{{ number_format($row->entries) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">Sign-ups by month</div>
                    @if ($report['growth']->isEmpty())
                        <div class="empty-state"><i class="bi bi-person-plus"></i>
                            <p class="mb-0 mt-2">No new customers in this period.</p></div>
                    @else
                        <table class="table table-app table-hover mb-0">
                            <thead class="table-light"><tr><th>Month</th><th class="text-end">New</th></tr></thead>
                            <tbody>
                                @foreach ($report['growth'] as $row)
                                    <tr>
                                        <td class="small">{{ $row->period }}</td>
                                        <td class="text-end small">{{ number_format($row->entries) }}</td>
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
