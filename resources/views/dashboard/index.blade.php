@extends('layouts.app')

@section('title', 'Dashboard')

{{--
    Each panel is wrapped in @isset. The controller supplies a panel's data
    only for abilities this user holds, so a missing variable is the signal
    that the panel should not be drawn — and its queries were never run.
--}}

@section('content')

    {{-- Alerts: what needs attention today ------------------------------- --}}
    @isset($alerts)
        @if ($alerts['overdueAccounts'] > 0)
            <div class="alert alert-danger d-flex flex-wrap align-items-center gap-3 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                <div class="flex-grow-1">
                    <strong>{{ number_format($alerts['overdueAccounts']) }}</strong>
                    account{{ $alerts['overdueAccounts'] === 1 ? '' : 's' }} overdue, totalling
                    <strong>&#8369;{{ number_format((float) $alerts['overdueAmount'], 2) }}</strong>.
                    @if ($alerts['oldestUnpaid'])
                        Oldest unpaid invoice
                        <a href="{{ route('invoices.show', $alerts['oldestUnpaid']) }}" class="alert-link">
                            {{ $alerts['oldestUnpaid']->invoice_number }}
                        </a>
                        was due {{ $alerts['oldestUnpaid']->due_date->diffForHumans() }}.
                    @endif
                </div>
                <a href="{{ route('invoices.index', ['status' => 'overdue']) }}" class="btn btn-sm btn-danger">
                    Review
                </a>
            </div>
        @endif
    @endisset

    {{-- Customer statistics --------------------------------------------- --}}
    @isset($customerStats)
        <h2 class="h6 text-secondary text-uppercase mb-2" style="letter-spacing:.05em;">Customers</h2>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg">
                <x-stat label="Total customers" :value="$customerStats['total']" />
            </div>
            <div class="col-6 col-lg">
                <x-stat label="Active" :value="$customerStats['active']" accent="success" />
            </div>
            <div class="col-6 col-lg">
                <x-stat label="Inactive" :value="$customerStats['inactive']" accent="secondary" />
            </div>
            <div class="col-6 col-lg">
                <x-stat label="Suspended" :value="$customerStats['suspended']" accent="danger" />
            </div>
            <div class="col-12 col-lg">
                <x-stat label="New this month" :value="$customerStats['newThisMonth']" accent="primary" />
            </div>
        </div>
    @endisset

    {{-- Billing statistics ---------------------------------------------- --}}
    @isset($billingStats)
        <h2 class="h6 text-secondary text-uppercase mb-2" style="letter-spacing:.05em;">Billing</h2>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <x-stat label="Total invoiced" :value="$billingStats['totalInvoiced']" money />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat label="Total paid" :value="$billingStats['totalPaid']" money accent="success" />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat label="Outstanding" :value="$billingStats['totalOutstanding']" money accent="navy" />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat label="Overdue" :value="$billingStats['totalOverdue']" money accent="danger" />
            </div>
        </div>
    @endisset

    {{-- Financial statistics -------------------------------------------- --}}
    @isset($financialStats)
        <h2 class="h6 text-secondary text-uppercase mb-2" style="letter-spacing:.05em;">Finance</h2>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <x-stat label="Revenue this month" :value="$financialStats['revenueThisMonth']" money accent="success" />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat label="Revenue this year" :value="$financialStats['revenueThisYear']" money />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat label="Expenses this month" :value="$financialStats['expensesThisMonth']" money accent="danger" />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat label="Net this month"
                        :value="$financialStats['netThisMonth']"
                        money
                        :accent="bccomp($financialStats['netThisMonth'], '0', 2) === -1 ? 'danger' : 'navy'" />
            </div>
        </div>
    @endisset

    {{-- Service statistics ---------------------------------------------- --}}
    @isset($serviceStats)
        <h2 class="h6 text-secondary text-uppercase mb-2" style="letter-spacing:.05em;">Services</h2>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <x-stat label="Active services" :value="$serviceStats['active']" accent="success" />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat label="Suspended" :value="$serviceStats['suspended']" accent="danger" />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat label="Expired" :value="$serviceStats['expired']" accent="secondary" />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat label="Pending installation" :value="$serviceStats['pendingInstallation']" accent="primary" />
            </div>
        </div>
    @endisset

    {{-- Charts ----------------------------------------------------------- --}}
    <div class="row g-3 mb-4">
        @isset($revenueTrend)
            <div class="col-12 col-xl-8">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">
                        Monthly revenue and payments
                    </div>
                    <div class="card-body">
                        <canvas id="chart-revenue" height="110"
                                data-chart='@json($revenueTrend)'
                                aria-label="Monthly revenue and payment count for the last twelve months"
                                role="img"></canvas>
                    </div>
                </div>
            </div>
        @endisset

        @isset($invoiceMix)
            <div class="col-12 col-xl-4">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">
                        Invoice status
                    </div>
                    <div class="card-body">
                        @if (empty($invoiceMix['values']))
                            <div class="empty-state"><i class="bi bi-receipt"></i>
                                <p class="mb-0 mt-2">No invoices yet.</p></div>
                        @else
                            <canvas id="chart-invoices" height="220"
                                    data-chart='@json($invoiceMix)'
                                    aria-label="Invoice status distribution" role="img"></canvas>
                        @endif
                    </div>
                </div>
            </div>
        @endisset

        @isset($customerTrend)
            <div class="col-12 col-xl-8">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">
                        New customers per month
                    </div>
                    <div class="card-body">
                        <canvas id="chart-customers" height="110"
                                data-chart='@json($customerTrend)'
                                aria-label="New customers per month for the last twelve months"
                                role="img"></canvas>
                    </div>
                </div>
            </div>
        @endisset

        @isset($serviceMix)
            <div class="col-12 col-xl-4">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">
                        Services by state
                    </div>
                    <div class="card-body">
                        @if (empty($serviceMix['values']))
                            <div class="empty-state"><i class="bi bi-hdd-network"></i>
                                <p class="mb-0 mt-2">No services yet.</p></div>
                        @else
                            <canvas id="chart-services" height="220"
                                    data-chart='@json($serviceMix)'
                                    aria-label="Services by state" role="img"></canvas>
                        @endif
                    </div>
                </div>
            </div>
        @endisset
    </div>

    {{-- Attention list --------------------------------------------------- --}}
    @isset($alerts)
        @if ($alerts['needingAttention']->isNotEmpty())
            <div class="card border-0 mb-4">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-navy">Customers requiring attention</span>
                    @can('invoices.view')
                        <a href="{{ route('reports.outstanding') }}" class="btn btn-sm btn-outline-primary">
                            Outstanding report
                        </a>
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table table-app table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Customer</th><th>Oldest due</th><th class="text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($alerts['needingAttention'] as $row)
                                <tr>
                                    <td class="small">
                                        <a href="{{ route('customers.show', $row->customer_id) }}"
                                           class="text-decoration-none">
                                            {{ $row->first_name }} {{ $row->last_name }}
                                        </a>
                                        <div class="text-secondary"><code>{{ $row->account_number }}</code></div>
                                    </td>
                                    <td class="small text-secondary">
                                        {{ \Illuminate\Support\Carbon::parse($row->oldest_due)->format('d M Y') }}
                                    </td>
                                    <td class="text-end fw-medium text-danger">
                                        &#8369;{{ number_format((float) $row->balance, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endisset

    {{-- Recent activity --------------------------------------------------- --}}
    <div class="row g-3">
        @isset($recentPayments)
            <div class="col-12 col-xl-4">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <span class="fw-semibold text-navy">Recent payments</span>
                        <a href="{{ route('payments.index') }}" class="small text-decoration-none">All</a>
                    </div>
                    @if ($recentPayments->isEmpty())
                        <div class="empty-state"><i class="bi bi-cash-coin"></i>
                            <p class="mb-0 mt-2">No payments recorded.</p></div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($recentPayments as $payment)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="small">
                                        <a href="{{ route('payments.show', $payment) }}" class="text-decoration-none">
                                            {{ $payment->customer?->full_name ?? 'Unknown' }}
                                        </a>
                                        <div class="text-secondary">
                                            {{ $payment->payment_date->format('d M Y') }}
                                            &middot; {{ $payment->payment_method->label() }}
                                        </div>
                                    </div>
                                    <span class="fw-medium small">
                                        &#8369;{{ number_format((float) $payment->amount, 2) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endisset

        @isset($recentInvoices)
            <div class="col-12 col-xl-4">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <span class="fw-semibold text-navy">Recent invoices</span>
                        <a href="{{ route('invoices.index') }}" class="small text-decoration-none">All</a>
                    </div>
                    @if ($recentInvoices->isEmpty())
                        <div class="empty-state"><i class="bi bi-receipt"></i>
                            <p class="mb-0 mt-2">No invoices yet.</p></div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($recentInvoices as $invoice)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="small">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="text-decoration-none">
                                            {{ $invoice->invoice_number }}
                                        </a>
                                        <div class="text-secondary">
                                            {{ $invoice->customer?->full_name ?? 'Unknown' }}
                                        </div>
                                    </div>
                                    <span class="badge {{ $invoice->status->badgeClass() }}">
                                        {{ $invoice->status->label() }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endisset

        @isset($recentCustomers)
            <div class="col-12 col-xl-4">
                <div class="card border-0 h-100">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <span class="fw-semibold text-navy">Recent customers</span>
                        <a href="{{ route('customers.index') }}" class="small text-decoration-none">All</a>
                    </div>
                    @if ($recentCustomers->isEmpty())
                        <div class="empty-state"><i class="bi bi-people"></i>
                            <p class="mb-0 mt-2">No customers yet.</p></div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($recentCustomers as $customer)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="small">
                                        <a href="{{ route('customers.show', $customer) }}" class="text-decoration-none">
                                            {{ $customer->full_name }}
                                        </a>
                                        <div class="text-secondary"><code>{{ $customer->account_number }}</code></div>
                                    </div>
                                    <span class="badge {{ $customer->status->badgeClass() }}">
                                        {{ $customer->status->label() }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endisset
    </div>

    @if (empty($customerStats) && empty($billingStats) && empty($serviceStats) && empty($financialStats))
        <div class="empty-state">
            <i class="bi bi-speedometer2"></i>
            <p class="mb-0 mt-2">Your role does not include access to any dashboard statistics.</p>
        </div>
    @endif
@endsection
