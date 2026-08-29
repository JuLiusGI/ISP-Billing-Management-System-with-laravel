@extends('layouts.app')

@section('title', 'Customer subscriptions')
@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Subscriptions</li>
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h5 mb-0 text-navy">Customer subscriptions</h2>
            <p class="small text-secondary mb-0">{{ number_format($subscriptions->total()) }} found</p>
        </div>

        @can('create', App\Models\Subscription::class)
            <a href="{{ route('subscriptions.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New subscription
            </a>
        @endcan
    </div>

    <div class="card border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('subscriptions.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label for="search" class="form-label small">Search</label>
                    <input type="search" name="search" id="search" class="form-control form-control-sm"
                           value="{{ request('search') }}"
                           placeholder="Subscription code, username, account no. or name">
                </div>

                <div class="col-6 col-md-2">
                    <label for="status" class="form-label small">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label for="plan" class="form-label small">Plan</label>
                    <select name="plan" id="plan" class="form-select form-select-sm">
                        <option value="">All plans</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected((int) request('plan') === $plan->id)>
                                {{ $plan->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="{{ route('subscriptions.index') }}" class="btn btn-sm btn-light border">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0">
        @if ($subscriptions->isEmpty())
            <div class="empty-state">
                <i class="bi bi-wifi"></i>
                <p class="mb-1 mt-2">No subscriptions match these filters.</p>
                @can('create', App\Models\Subscription::class)
                    <a href="{{ route('subscriptions.create') }}" class="small">Create the first subscription</a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-app table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Customer</th>
                            <th>Plan</th>
                            <th class="text-end">Monthly</th>
                            <th>Billing day</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subscriptions as $subscription)
                            <tr>
                                <td><code class="small">{{ $subscription->subscription_code }}</code></td>
                                <td>
                                    <a href="{{ route('customers.show', $subscription->customer) }}"
                                       class="fw-medium text-decoration-none">
                                        {{ $subscription->customer->full_name }}
                                    </a>
                                    <div class="small text-secondary">{{ $subscription->customer->account_number }}</div>
                                </td>
                                <td class="small">
                                    <div>{{ $subscription->internetPlan->name }}</div>
                                    <div class="text-secondary">{{ $subscription->internetPlan->speed_label }}</div>
                                </td>
                                <td class="text-end">
                                    <div class="fw-medium">&#8369;{{ number_format((float) $subscription->net_monthly_rate, 2) }}</div>
                                    @if ((float) $subscription->discount_amount > 0)
                                        <div class="small text-secondary">
                                            after &#8369;{{ number_format((float) $subscription->discount_amount, 2) }} off
                                        </div>
                                    @endif
                                </td>
                                <td class="small">{{ $subscription->billing_day }}</td>
                                <td>
                                    <span class="badge {{ $subscription->status->badgeClass() }}">
                                        {{ $subscription->status->label() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('subscriptions.show', $subscription) }}"
                                       class="btn btn-sm btn-light border">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($subscriptions->hasPages())
                <div class="card-footer bg-white border-top">
                    {{ $subscriptions->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
@endsection
