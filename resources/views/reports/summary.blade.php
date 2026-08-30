@extends('layouts.app')

@section('title', 'Financial summary')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active" aria-current="page">Financial summary</li>
@endsection

@section('content')
    <x-report-shell title="Financial Summary"
                    description="Gross revenue less expenses. Revenue counts completed payments only."
                    :from="$from" :to="$to">

        <x-report-period :action="route('reports.summary')" :from="$from" :to="$to" />

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <x-stat label="Gross revenue" :value="$report['grossRevenue']" money accent="success" />
            </div>
            <div class="col-12 col-md-4">
                <x-stat label="Expenses" :value="$report['expenses']" money accent="danger" />
            </div>
            <div class="col-12 col-md-4">
                <x-stat label="Net revenue"
                        :value="$report['net']"
                        money
                        :accent="bccomp($report['net'], '0', 2) === -1 ? 'danger' : 'navy'"
                        :hint="'Margin '.$report['margin'].'%'" />
            </div>
        </div>

        <div class="card border-0">
            <div class="card-header bg-white border-bottom fw-semibold text-navy">Month by month</div>

            @if ($report['months']->isEmpty())
                <div class="empty-state">
                    <i class="bi bi-calendar3"></i>
                    <p class="mb-0 mt-2">Nothing recorded in this period.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-app table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Month</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Expenses</th>
                                <th class="text-end">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['months'] as $month)
                                @php $loss = bccomp($month->net, '0', 2) === -1; @endphp
                                <tr>
                                    <td class="small">
                                        {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month->period)->format('F Y') }}
                                    </td>
                                    <td class="text-end small">&#8369;{{ number_format((float) $month->revenue, 2) }}</td>
                                    <td class="text-end small">&#8369;{{ number_format((float) $month->expenses, 2) }}</td>
                                    <td class="text-end fw-medium {{ $loss ? 'text-danger' : '' }}">
                                        &#8369;{{ number_format((float) $month->net, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th>Total</th>
                                <th class="text-end">&#8369;{{ number_format((float) $report['grossRevenue'], 2) }}</th>
                                <th class="text-end">&#8369;{{ number_format((float) $report['expenses'], 2) }}</th>
                                <th class="text-end">&#8369;{{ number_format((float) $report['net'], 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </x-report-shell>
@endsection
