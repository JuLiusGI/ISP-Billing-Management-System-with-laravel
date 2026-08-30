@extends('layouts.app')

@section('title', 'Reports')
@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Reports</li>
@endsection

@php
    // Each report is listed only if its ability allows opening it, so the hub
    // never offers a link that would 403.
    $groups = [
        'Financial' => [
            ['ability' => 'reports.financial', 'route' => 'reports.summary', 'icon' => 'calculator',
             'title' => 'Financial Summary', 'blurb' => 'Gross revenue less expenses, month by month.'],
            ['ability' => 'reports.financial', 'route' => 'reports.revenue', 'icon' => 'graph-up-arrow',
             'title' => 'Revenue Report', 'blurb' => 'Money received over time and by payment method.'],
            ['ability' => 'expenses.view', 'route' => 'reports.expenses', 'icon' => 'wallet2',
             'title' => 'Expense Report', 'blurb' => 'Operating costs by category and period.'],
        ],
        'Billing' => [
            ['ability' => 'payments.view', 'route' => 'reports.payments', 'icon' => 'cash-coin',
             'title' => 'Payment Report', 'blurb' => 'Every payment taken in the period.'],
            ['ability' => 'invoices.view', 'route' => 'reports.billing', 'icon' => 'receipt',
             'title' => 'Billing Report', 'blurb' => 'Invoices issued and where they ended up.'],
            ['ability' => 'invoices.view', 'route' => 'reports.outstanding', 'icon' => 'hourglass-split',
             'title' => 'Outstanding Report', 'blurb' => 'Receivables aged by how long they have been owed.'],
            ['ability' => 'invoices.view', 'route' => 'reports.overdue', 'icon' => 'exclamation-triangle',
             'title' => 'Overdue Report', 'blurb' => 'Invoices past their due date, by age.'],
        ],
        'Operations' => [
            ['ability' => 'reports.operational', 'route' => 'reports.customers', 'icon' => 'people',
             'title' => 'Customer Report', 'blurb' => 'Customer base by status, type and growth.'],
            ['ability' => 'reports.operational', 'route' => 'reports.services', 'icon' => 'hdd-network',
             'title' => 'Service Report', 'blurb' => 'Services by state and plan, plus recurring revenue.'],
        ],
    ];
@endphp

@section('content')
    <div class="mb-3">
        <h2 class="h5 mb-0 text-navy">Reports</h2>
        <p class="small text-secondary mb-0">
            Every report is filterable by date range and exports to CSV.
        </p>
    </div>

    @php $anyVisible = false; @endphp

    @foreach ($groups as $heading => $reports)
        @php
            $visible = array_filter($reports, fn ($r) => auth()->user()->can($r['ability']));
            $anyVisible = $anyVisible || count($visible) > 0;
        @endphp

        @if ($visible)
            <div class="app-sidebar__heading text-secondary ps-0">{{ $heading }}</div>
            <div class="row g-3 mb-3">
                @foreach ($visible as $report)
                    <div class="col-12 col-md-6 col-xl-4">
                        <a href="{{ route($report['route']) }}"
                           class="card border-0 h-100 text-decoration-none">
                            <div class="card-body d-flex gap-3">
                                <span class="badge text-bg-light border p-2 align-self-start">
                                    <i class="bi bi-{{ $report['icon'] }} fs-5 text-navy"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold text-navy">{{ $report['title'] }}</div>
                                    <div class="small text-secondary">{{ $report['blurb'] }}</div>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    @endforeach

    @unless ($anyVisible)
        <div class="empty-state">
            <i class="bi bi-bar-chart"></i>
            <p class="mb-0 mt-2">Your role does not include access to any reports.</p>
        </div>
    @endunless
@endsection
