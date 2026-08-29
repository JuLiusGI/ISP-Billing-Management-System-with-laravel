@extends('layouts.app')

@section('title', 'Internet plans')
@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Internet plans</li>
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h5 mb-0 text-navy">Internet plans</h2>
            <p class="small text-secondary mb-0">{{ number_format($plans->total()) }} plan(s) found</p>
        </div>

        @can('create', App\Models\InternetPlan::class)
            <a href="{{ route('plans.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add plan
            </a>
        @endcan
    </div>

    <div class="card border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('plans.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label for="search" class="form-label small">Search</label>
                    <input type="search" name="search" id="search" class="form-control form-control-sm"
                           value="{{ request('search') }}" placeholder="Plan name or code">
                </div>

                <div class="col-6 col-md-3">
                    <label for="status" class="form-label small">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label for="cycle" class="form-label small">Billing cycle</label>
                    <select name="cycle" id="cycle" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($cycles as $cycle)
                            <option value="{{ $cycle->value }}" @selected(request('cycle') === $cycle->value)>
                                {{ $cycle->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="{{ route('plans.index') }}" class="btn btn-sm btn-light border">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0">
        @if ($plans->isEmpty())
            <div class="empty-state">
                <i class="bi bi-diagram-3"></i>
                <p class="mb-1 mt-2">No plans match these filters.</p>
                @can('create', App\Models\InternetPlan::class)
                    <a href="{{ route('plans.create') }}" class="small">Create the first plan</a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-app table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Plan</th>
                            <th>Speed</th>
                            <th class="text-end">Monthly</th>
                            <th class="text-end">Install / activation</th>
                            <th>Cycle</th>
                            <th>Subscribers</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plans as $plan)
                            <tr class="{{ $plan->is_active ? '' : 'opacity-75' }}">
                                <td><code class="small">{{ $plan->plan_code }}</code></td>
                                <td>
                                    <div class="fw-medium">{{ $plan->name }}</div>
                                    @if ($plan->description)
                                        <div class="small text-secondary text-truncate" style="max-width: 18rem;">
                                            {{ $plan->description }}
                                        </div>
                                    @endif
                                </td>
                                <td class="small">{{ $plan->speed_label }}</td>
                                <td class="text-end fw-medium">
                                    &#8369;{{ number_format((float) $plan->monthly_price, 2) }}
                                </td>
                                <td class="text-end small text-secondary">
                                    &#8369;{{ number_format((float) $plan->installation_fee, 2) }}
                                    /
                                    &#8369;{{ number_format((float) $plan->activation_fee, 2) }}
                                </td>
                                <td class="small">{{ $plan->billing_cycle->label() }}</td>
                                <td class="small">
                                    <span class="badge text-bg-light border">
                                        {{ $plan->active_subscriptions_count }} active
                                    </span>
                                    @if ($plan->subscriptions_count > $plan->active_subscriptions_count)
                                        <div class="text-secondary">{{ $plan->subscriptions_count }} total</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $plan->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border" type="button"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                aria-label="Actions for {{ $plan->name }}">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            @can('update', $plan)
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('plans.edit', $plan) }}">
                                                        <i class="bi bi-pencil me-2"></i>Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <form method="POST" action="{{ route('plans.toggle', $plan) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="bi bi-{{ $plan->is_active ? 'pause' : 'play' }}-circle me-2"></i>
                                                            {{ $plan->is_active ? 'Deactivate' : 'Activate' }}
                                                        </button>
                                                    </form>
                                                </li>
                                            @endcan

                                            @can('delete', $plan)
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="{{ route('plans.destroy', $plan) }}"
                                                          data-confirm="Delete the {{ $plan->name }} plan?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="bi bi-trash me-2"></i>Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            @elsecan('update', $plan)
                                                <li>
                                                    <span class="dropdown-item-text small text-secondary"
                                                          style="max-width:15rem;white-space:normal;">
                                                        In use by subscriptions, so it can only be deactivated.
                                                    </span>
                                                </li>
                                            @endcan
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($plans->hasPages())
                <div class="card-footer bg-white border-top">
                    {{ $plans->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>

    <p class="small text-secondary mt-3 mb-0">
        Changing a plan's price affects new subscriptions only. Existing subscriptions keep the rate
        they were signed up on, and issued invoices never change.
    </p>
@endsection
