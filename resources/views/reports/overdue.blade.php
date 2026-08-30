@extends('layouts.app')

@section('title', 'Overdue report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active" aria-current="page">Overdue</li>
@endsection

@section('content')
    <x-report-shell title="Overdue Report"
                    description="Invoices past their due date, grouped by how far past. Like ageing, this describes today rather than a chosen range.">

        <x-report-period :action="route('reports.overdue')" :dated="false" />

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6">
                <x-stat label="Overdue balance" :value="$report['total']" money accent="danger" />
            </div>
            <div class="col-12 col-md-6">
                <x-stat label="Overdue invoices" :value="$report['count']" />
            </div>
        </div>

        <div class="card border-0">
            <div class="card-header bg-white border-bottom fw-semibold text-navy">By age</div>

            @if ($report['buckets']->isEmpty())
                <div class="empty-state">
                    <i class="bi bi-emoji-smile"></i>
                    <p class="mb-0 mt-2">Nothing is overdue.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-app table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Days overdue</th>
                                <th class="text-end">Invoices</th>
                                <th class="text-end">Balance</th>
                                <th class="text-end">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['buckets'] as $row)
                                @php
                                    $share = (float) $report['total'] > 0
                                        ? ((float) $row->total / (float) $report['total']) * 100 : 0;
                                @endphp
                                <tr>
                                    <td class="small">{{ $row->bucket }}</td>
                                    <td class="text-end small">{{ number_format($row->entries) }}</td>
                                    <td class="text-end small">&#8369;{{ number_format((float) $row->total, 2) }}</td>
                                    <td class="text-end small text-secondary">{{ number_format($share, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="card-footer bg-white border-top small text-secondary">
                Use <a href="{{ route('invoices.index', ['status' => 'overdue']) }}">Overdue Invoices</a>
                to act on individual accounts.
            </div>
        </div>
    </x-report-shell>
@endsection
