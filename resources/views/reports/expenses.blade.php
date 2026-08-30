@extends('layouts.app')

@section('title', 'Expense report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active" aria-current="page">Expenses</li>
@endsection

@section('content')
    <x-report-shell title="Expense Report"
                    description="Operating costs. Archived entries are excluded."
                    :from="$from" :to="$to">

        <x-report-period :action="route('reports.expenses')" :from="$from" :to="$to">
            <div class="col-6 col-lg-3">
                <label for="category" class="form-label small">Category</label>
                <select name="category" id="category" class="form-select form-select-sm">
                    <option value="">All categories</option>
                    @foreach ($categories as $option)
                        <option value="{{ $option->id }}" @selected($categoryId === $option->id)>
                            {{ $option->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </x-report-period>

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6">
                <x-stat label="Total spend" :value="$report['total']" money accent="danger" />
            </div>
            <div class="col-12 col-md-6">
                <x-stat label="Entries" :value="$report['count']" />
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">By category</div>
                    @if ($report['byCategory']->isEmpty())
                        <div class="empty-state"><i class="bi bi-tags"></i>
                            <p class="mb-0 mt-2">No expenses in this period.</p></div>
                    @else
                        <table class="table table-app table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Category</th><th class="text-end">Entries</th>
                                    <th class="text-end">Total</th><th class="text-end">Share</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($report['byCategory'] as $row)
                                    @php
                                        $share = (float) $report['total'] > 0
                                            ? ((float) $row->total / (float) $report['total']) * 100 : 0;
                                    @endphp
                                    <tr>
                                        <td class="small">{{ $row->name }}</td>
                                        <td class="text-end small">{{ number_format($row->entries) }}</td>
                                        <td class="text-end small">&#8369;{{ number_format((float) $row->total, 2) }}</td>
                                        <td class="text-end small text-secondary">{{ number_format($share, 1) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">Over time</div>
                    @if ($report['overTime']->isEmpty())
                        <div class="empty-state"><i class="bi bi-graph-down"></i>
                            <p class="mb-0 mt-2">Nothing to plot.</p></div>
                    @else
                        @php $peak = (float) $report['overTime']->max('total') ?: 1; @endphp
                        <div class="card-body">
                            @foreach ($report['overTime'] as $row)
                                <div class="d-flex justify-content-between small">
                                    <span>{{ $row->period }}</span>
                                    <span class="text-secondary">&#8369;{{ number_format((float) $row->total, 2) }}</span>
                                </div>
                                <div class="progress mb-2" style="height:4px;" role="progressbar"
                                     aria-label="{{ $row->period }}"
                                     aria-valuenow="{{ round(((float) $row->total / $peak) * 100) }}"
                                     aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-danger"
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
