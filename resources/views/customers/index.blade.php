@extends('layouts.app')

@section('title', request()->boolean('archived') ? 'Archived customers' : 'Customers')
@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Customers</li>
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h5 mb-0 text-navy">
                {{ request()->boolean('archived') ? 'Archived customers' : 'Customers' }}
            </h2>
            <p class="small text-secondary mb-0">{{ number_format($customers->total()) }} found</p>
        </div>

        <div class="d-flex gap-2">
            @if (request()->boolean('archived'))
                <a href="{{ route('customers.index') }}" class="btn btn-sm btn-light border">
                    <i class="bi bi-arrow-left me-1"></i> Active list
                </a>
            @else
                <a href="{{ route('customers.index', ['archived' => 1]) }}" class="btn btn-sm btn-light border">
                    <i class="bi bi-archive me-1"></i> Archived
                </a>
            @endif

            @can('create', App\Models\Customer::class)
                <a href="{{ route('customers.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-person-plus me-1"></i> Add customer
                </a>
            @endcan
        </div>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('customers.index') }}" class="row g-2 align-items-end">
                @if (request()->boolean('archived'))
                    <input type="hidden" name="archived" value="1">
                @endif

                <div class="col-12 col-lg-4">
                    <label for="search" class="form-label small">Search</label>
                    <input type="search" name="search" id="search" class="form-control form-control-sm"
                           value="{{ request('search') }}"
                           placeholder="Account no., name, email or contact">
                </div>

                <div class="col-6 col-lg-2">
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

                <div class="col-6 col-lg-2">
                    <label for="account_status" class="form-label small">Billing standing</label>
                    <select name="account_status" id="account_status" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($accountStatuses as $status)
                            <option value="{{ $status->value }}" @selected(request('account_status') === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label for="type" class="form-label small">Type</label>
                    <select name="type" id="type" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(request('type') === $type->value)>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="{{ route('customers.index', request()->boolean('archived') ? ['archived' => 1] : []) }}"
                       class="btn btn-sm btn-light border">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0">
        @if ($customers->isEmpty())
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <p class="mb-1 mt-2">No customers match these filters.</p>
                @can('create', App\Models\Customer::class)
                    <a href="{{ route('customers.create') }}" class="small">Add the first customer</a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-app table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Account #</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Service</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr>
                                <td><code class="small">{{ $customer->account_number }}</code></td>
                                <td>
                                    <div class="fw-medium">{{ $customer->full_name }}</div>
                                    <div class="small text-secondary">{{ $customer->customer_type->label() }}</div>
                                </td>
                                <td class="small">
                                    <div>{{ $customer->contact_number }}</div>
                                    <div class="text-secondary">{{ $customer->email ?: '—' }}</div>
                                </td>
                                <td class="small text-secondary">
                                    {{ $customer->primaryAddress?->municipality_city ?? '—' }}
                                </td>
                                <td>
                                    <span class="badge {{ $customer->status->badgeClass() }}">
                                        {{ $customer->status->label() }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $customer->connection_status->badgeClass() }}">
                                        {{ $customer->connection_status->label() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border" type="button"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                aria-label="Actions for {{ $customer->full_name }}">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            @if (! $customer->trashed())
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('customers.show', $customer) }}">
                                                        <i class="bi bi-eye me-2"></i>View profile
                                                    </a>
                                                </li>
                                                @can('update', $customer)
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('customers.edit', $customer) }}">
                                                            <i class="bi bi-pencil me-2"></i>Edit
                                                        </a>
                                                    </li>
                                                @endcan
                                                @can('delete', $customer)
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" action="{{ route('customers.destroy', $customer) }}"
                                                              data-confirm="Archive {{ $customer->full_name }}? Their records are kept.">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="bi bi-archive me-2"></i>Archive
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endcan
                                            @else
                                                @can('restore', $customer)
                                                    <li>
                                                        <form method="POST" action="{{ route('customers.restore', $customer->id) }}">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="bi bi-arrow-counterclockwise me-2"></i>Restore
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endcan
                                            @endif
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($customers->hasPages())
                <div class="card-footer bg-white border-top">
                    {{ $customers->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
@endsection
