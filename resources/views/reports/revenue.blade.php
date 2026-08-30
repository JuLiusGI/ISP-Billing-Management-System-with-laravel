@extends('layouts.app')

@section('title', 'Revenue report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active" aria-current="page">Revenue</li>
@endsection

@section('content')
    <x-report-shell title="Revenue Report"
                    description="Money received. Reversed and cancelled payments are excluded."
                    :from="$from" :to="$to">

        <x-report-period :action="route('reports.revenue')" :from="$from" :to="$to">
            <div class="col-6 col-lg-2">
                <label for="method" class="form-label small">Method</label>
                <select name="method" id="method" class="form-select form-select-sm">
                    <option value="">All methods</option>
                    @foreach ($methods as $option)
                        <option value="{{ $option->value }}" @selected($method === $option->value)>
                            {{ $option->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
        </x-report-period>

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <x-stat label="Total revenue" :value="$report['total']" money accent="success" />
            </div>
            <div class="col-12 col-md-4">
                <x-stat label="Payments received" :value="$report['count']" />
            </div>
            <div class="col-12 col-md-4">
                <x-stat label="Average payment" :value="$report['average']" money />
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">By payment method</div>
                    @if ($report['byMethod']->isEmpty())
                        <div class="empty-state"><i class="bi bi-cash-coin"></i>
                            <p class="mb-0 mt-2">No payments in this period.</p></div>
                    @else
                        <table class="table table-app table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Method</th><th class="text-end">Count</th><th class="text-end">Total</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($report['byMethod'] as $row)
                                    <tr>
                                        <td class="small">{{ $row->payment_method->label() }}</td>
                                        <td class="text-end small">{{ number_format($row->entries) }}</td>
                                        <td class="text-end small">&#8369;{{ number_format((float) $row->total, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <div class="col-12 col-lg-7">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">Over time</div>
                    @if ($report['overTime']->isEmpty())
                        <div class="empty-state"><i class="bi bi-graph-up"></i>
                            <p class="mb-0 mt-2">Nothing to plot.</p></div>
                    @else
                        @php $peak = (float) $report['overTime']->max('total') ?: 1; @endphp
                        <div class="card-body">
                            @foreach ($report['overTime'] as $row)
                                <div class="d-flex justify-content-between small">
                                    <span>{{ $row->period }}</span>
                                    <span class="text-secondary">
                                        &#8369;{{ number_format((float) $row->total, 2) }}
                                        <span class="ms-1">({{ $row->entries }})</span>
                                    </span>
                                </div>
                                <div class="progress mb-2" style="height:4px;" role="progressbar"
                                     aria-label="{{ $row->period }}"
                                     aria-valuenow="{{ round(((float) $row->total / $peak) * 100) }}"
                                     aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-success"
                                         style="width: {{ ((float) $row->total / $peak) * 100 }}%"></div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </x-report-shell>
@endsection
