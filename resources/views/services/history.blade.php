@extends('layouts.app')

@section('title', 'Service status history')
@section('breadcrumb')
    <li class="breadcrumb-item">Internet Services</li>
    <li class="breadcrumb-item active" aria-current="page">Status history</li>
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h5 mb-0 text-navy">Service status history</h2>
            <p class="small text-secondary mb-0">
                Every activation, suspension and reconnection, across all customers
            </p>
        </div>

        <a href="{{ route('services.index') }}" class="btn btn-sm btn-light border">
            <i class="bi bi-hdd-network me-1"></i> Service board
        </a>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('services.history') }}" class="row g-2 align-items-end">
                <div class="col-12 col-lg-4">
                    <label for="search" class="form-label small">Search</label>
                    <input type="search" name="search" id="search" class="form-control form-control-sm"
                           value="{{ request('search') }}" placeholder="Customer, account no. or subscription">
                </div>

                <div class="col-6 col-lg-2">
                    <label for="to_status" class="form-label small">Changed to</label>
                    <select name="to_status" id="to_status" class="form-select form-select-sm">
                        <option value="">Any status</option>
                        @foreach ($statuses as $case)
                            <option value="{{ $case->value }}" @selected(request('to_status') === $case->value)>
                                {{ $case->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label for="source" class="form-label small">Source</label>
                    <select name="source" id="source" class="form-select form-select-sm">
                        <option value="">Anyone</option>
                        <option value="manual" @selected(request('source') === 'manual')>A person</option>
                        <option value="automatic" @selected(request('source') === 'automatic')>The scheduler</option>
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label for="from" class="form-label small">From</label>
                    <input type="date" name="from" id="from" class="form-control form-control-sm"
                           value="{{ request('from') }}">
                </div>

                <div class="col-6 col-lg-2">
                    <label for="to" class="form-label small">To</label>
                    <input type="date" name="to" id="to" class="form-control form-control-sm"
                           value="{{ request('to') }}">
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="{{ route('services.history') }}" class="btn btn-sm btn-light border">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0">
        @if ($logs->isEmpty())
            <div class="empty-state">
                <i class="bi bi-clock-history"></i>
                <p class="mb-1 mt-2">No status changes match these filters.</p>
                <a href="{{ route('services.history') }}" class="small">Clear filters</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-app table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>When</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Change</th>
                            <th>Reason</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php
                                $from = $log->fromStatus();
                                $to = $log->toStatus();
                            @endphp
                            <tr>
                                <td class="small text-nowrap">
                                    {{ $log->created_at->format('d M Y') }}
                                    <div class="text-secondary">{{ $log->created_at->format('g:i A') }}</div>
                                </td>
                                <td class="small">
                                    @if ($log->customer)
                                        <a href="{{ route('customers.show', $log->customer) }}"
                                           class="text-decoration-none">{{ $log->customer->full_name }}</a>
                                        <div class="text-secondary">
                                            <code>{{ $log->customer->account_number }}</code>
                                        </div>
                                    @else
                                        <span class="text-secondary">Removed customer</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if ($log->subscription)
                                        <a href="{{ route('subscriptions.show', $log->subscription) }}"
                                           class="text-decoration-none">
                                            <code>{{ $log->subscription->subscription_code }}</code>
                                        </a>
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                                <td class="small text-nowrap">
                                    @if ($from)
                                        <span class="badge {{ $from->badgeClass() }}">{{ $from->label() }}</span>
                                    @else
                                        <span class="badge text-bg-light border">Created</span>
                                    @endif
                                    <i class="bi bi-arrow-right mx-1 text-secondary"></i>
                                    @if ($to)
                                        <span class="badge {{ $to->badgeClass() }}">{{ $to->label() }}</span>
                                    @else
                                        <span class="badge text-bg-light border">{{ $log->to_status }}</span>
                                    @endif
                                </td>
                                <td class="small text-secondary">{{ $log->reason ?: '—' }}</td>
                                <td class="small">
                                    @if ($log->is_automatic)
                                        <span class="badge text-bg-light border">
                                            <i class="bi bi-robot me-1"></i>Scheduler
                                        </span>
                                    @else
                                        {{ $log->changedBy?->full_name ?? 'System' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="card-footer bg-white border-top">
                    {{ $logs->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
@endsection
