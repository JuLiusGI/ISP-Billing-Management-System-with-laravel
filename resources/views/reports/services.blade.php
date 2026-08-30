@extends('layouts.app')

@section('title', 'Service report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active" aria-current="page">Services</li>
@endsection

@section('content')
    <x-report-shell title="Service Report"
                    description="Standing service totals; status changes are counted within the chosen range."
                    :from="$from" :to="$to">

        <x-report-period :action="route('reports.services')" :from="$from" :to="$to" />

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6">
                <x-stat label="Total services" :value="$report['total']" />
            </div>
            <div class="col-12 col-md-6">
                <x-stat label="Monthly recurring revenue" :value="$report['monthlyRecurring']" money accent="success"
                        hint="Active services only, after standing discounts" />
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">By state</div>
                    <table class="table table-app table-hover mb-0">
                        <thead class="table-light"><tr><th>State</th><th class="text-end">Services</th></tr></thead>
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

            <div class="col-12 col-lg-5">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">By plan</div>
                    <div class="table-responsive">
                        <table class="table table-app table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Plan</th><th class="text-end">Services</th><th class="text-end">Recurring</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($report['byPlan'] as $row)
                                    <tr>
                                        <td class="small">{{ $row->name }}</td>
                                        <td class="text-end small">{{ number_format($row->entries) }}</td>
                                        <td class="text-end small">&#8369;{{ number_format((float) $row->recurring, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-3">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">
                        Status changes in period
                    </div>
                    @if ($report['changes']->isEmpty())
                        <div class="empty-state"><i class="bi bi-clock-history"></i>
                            <p class="mb-0 mt-2">No changes recorded.</p></div>
                    @else
                        <table class="table table-app table-hover mb-0">
                            <thead class="table-light"><tr><th>Changed to</th><th class="text-end">Count</th></tr></thead>
                            <tbody>
                                @foreach ($report['changes'] as $row)
                                    <tr>
                                        <td class="small">
                                            {{ App\Enums\SubscriptionStatus::tryFrom($row->to_status)?->label() ?? $row->to_status }}
                                        </td>
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
