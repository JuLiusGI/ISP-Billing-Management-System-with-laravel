@extends('layouts.app')

@section('title', $status->label().' services')
@section('breadcrumb')
    <li class="breadcrumb-item">Internet Services</li>
    <li class="breadcrumb-item active" aria-current="page">{{ $status->label() }} services</li>
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h5 mb-0 text-navy">{{ $status->label() }} services</h2>
            <p class="small text-secondary mb-0">
                {{ number_format($services->total()) }} service(s) in this state
            </p>
        </div>

        <a href="{{ route('services.history') }}" class="btn btn-sm btn-light border">
            <i class="bi bi-clock-history me-1"></i> Status history
        </a>
    </div>

    @unless ($provisioningEnabled)
        <div class="alert alert-secondary d-flex align-items-start gap-2 py-2 small" role="alert">
            <i class="bi bi-hdd-network mt-1"></i>
            <div>
                No network backend is configured. Status changes are recorded here and drive
                billing, but nothing is pushed to a router yet.
            </div>
        </div>
    @endunless

    {{-- Status board ---------------------------------------------------- --}}
    <div class="row g-2 mb-3">
        @foreach (App\Enums\SubscriptionStatus::cases() as $case)
            <div class="col-6 col-md">
                <a href="{{ route('services.index', ['status' => $case->value]) }}"
                   class="card border-0 h-100 text-decoration-none {{ $case === $status ? 'border-bottom border-4 border-danger' : '' }}">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-{{ $case->icon() }} {{ $case === $status ? 'text-danger' : 'text-secondary' }}"></i>
                            <span class="small {{ $case === $status ? 'fw-semibold text-navy' : 'text-secondary' }}">
                                {{ $case->label() }}
                            </span>
                        </div>
                        <div class="fs-5 fw-bold {{ $case === $status ? 'text-navy' : 'text-secondary' }}">
                            {{ number_format($counts[$case->value]) }}
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    {{-- Filters --------------------------------------------------------- --}}
    <div class="card border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('services.index') }}" class="row g-2 align-items-end">
                <input type="hidden" name="status" value="{{ $status->value }}">

                <div class="col-12 col-lg-5">
                    <label for="search" class="form-label small">Search</label>
                    <input type="search" name="search" id="search" class="form-control form-control-sm"
                           value="{{ request('search') }}"
                           placeholder="Account no., customer, subscription, username or IP">
                </div>

                <div class="col-6 col-lg-3">
                    <label for="plan" class="form-label small">Plan</label>
                    <select name="plan" id="plan" class="form-select form-select-sm">
                        <option value="">All plans</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected(request('plan') == $plan->id)>
                                {{ $plan->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label for="connection_type" class="form-label small">Connection</label>
                    <select name="connection_type" id="connection_type" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach (App\Enums\ConnectionType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(request('connection_type') === $type->value)>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="{{ route('services.index', ['status' => $status->value]) }}"
                       class="btn btn-sm btn-light border">Clear</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Services -------------------------------------------------------- --}}
    <div class="card border-0">
        @if ($services->isEmpty())
            <div class="empty-state">
                <i class="bi bi-{{ $status->icon() }}"></i>
                <p class="mb-1 mt-2">No {{ strtolower($status->label()) }} services match these filters.</p>
                <a href="{{ route('services.index', ['status' => $status->value]) }}" class="small">Clear filters</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-app table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Plan</th>
                            <th>Network</th>
                            <th>Last change</th>
                            <th class="text-end">Service actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($services as $service)
                            <tr>
                                <td>
                                    <a href="{{ route('customers.show', $service->customer) }}"
                                       class="fw-medium text-decoration-none">
                                        {{ $service->customer->full_name }}
                                    </a>
                                    <div class="small text-secondary">
                                        <code>{{ $service->customer->account_number }}</code>
                                    </div>
                                </td>
                                <td>
                                    <a href="{{ route('subscriptions.show', $service) }}" class="text-decoration-none">
                                        <code class="small">{{ $service->subscription_code }}</code>
                                    </a>
                                    <div class="small text-secondary">
                                        {{ $service->connection_type->label() }}
                                    </div>
                                </td>
                                <td class="small">
                                    {{ $service->internetPlan->name }}
                                    <div class="text-secondary">{{ $service->internetPlan->speed_label }}</div>
                                </td>
                                <td class="small text-secondary">
                                    <div>{{ $service->username ?: '—' }}</div>
                                    <div>{{ $service->static_ip ?: 'Dynamic' }}</div>
                                </td>
                                <td class="small text-secondary">
                                    {{ $service->updated_at->diffForHumans() }}
                                </td>
                                <td class="text-end">
                                    @php $transitions = $service->status->allowedTransitions(); @endphp

                                    @can('manageStatus', $service)
                                        @if (empty($transitions))
                                            <span class="small text-secondary">No actions</span>
                                        @else
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light border dropdown-toggle" type="button"
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    Change
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    @foreach ($transitions as $target)
                                                        <li>
                                                            <form method="POST"
                                                                  action="{{ route('subscriptions.status', $service) }}"
                                                                  data-confirm="{{ $target->actionLabel() }} the service for {{ $service->customer->full_name }}?">
                                                                @csrf
                                                                @method('PATCH')
                                                                <input type="hidden" name="status" value="{{ $target->value }}">
                                                                <input type="hidden" name="reason"
                                                                       value="Changed from the service board">
                                                                <button type="submit"
                                                                        class="dropdown-item {{ $target === App\Enums\SubscriptionStatus::Cancelled ? 'text-danger' : '' }}">
                                                                    <i class="bi bi-{{ $target->icon() }} me-2"></i>
                                                                    {{ $target->actionLabel() }}
                                                                </button>
                                                            </form>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    @else
                                        <a href="{{ route('subscriptions.show', $service) }}"
                                           class="btn btn-sm btn-light border">View</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($services->hasPages())
                <div class="card-footer bg-white border-top">
                    {{ $services->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
@endsection
