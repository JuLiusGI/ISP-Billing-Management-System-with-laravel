@extends('layouts.app')

@section('title', 'Users')
@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Users</li>
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h5 mb-0 text-navy">Staff accounts</h2>
            <p class="small text-secondary mb-0">{{ $users->total() }} account(s) found</p>
        </div>

        @can('create', App\Models\User::class)
            <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Add user
            </a>
        @endcan
    </div>

    {{-- Filters are submitted to the server; the query never happens in JS. --}}
    <div class="card border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('users.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label for="search" class="form-label small">Search</label>
                    <input type="search" name="search" id="search" class="form-control form-control-sm"
                           value="{{ request('search') }}" placeholder="Name or email">
                </div>

                <div class="col-6 col-md-3">
                    <label for="status" class="form-label small">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label for="role" class="form-label small">Role</label>
                    <select name="role" id="role" class="form-select form-select-sm">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" @selected(request('role') === $role->name)>
                                {{ $role->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-1 d-grid">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0">
        @if ($users->isEmpty())
            <div class="empty-state">
                <i class="bi bi-search"></i>
                <p class="mb-1 mt-2">No users match these filters.</p>
                <a href="{{ route('users.index') }}" class="small">Clear filters</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-app table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Roles</th>
                            <th>Status</th>
                            <th>Last sign-in</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="app-avatar">{{ $user->initials }}</span>
                                        <div>
                                            <div class="fw-medium">{{ $user->full_name }}</div>
                                            @if (auth()->user()->is($user))
                                                <span class="badge text-bg-light border">You</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="small">
                                    <div>{{ $user->email }}</div>
                                    <div class="text-secondary">{{ $user->phone ?: '—' }}</div>
                                </td>
                                <td class="small">{{ $user->roles->pluck('display_name')->join(', ') ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $user->status->badgeClass() }}">
                                        {{ $user->status->label() }}
                                    </span>
                                </td>
                                <td class="small text-secondary">
                                    {{ $user->last_login_at?->diffForHumans() ?? 'Never' }}
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border" type="button"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                aria-label="Actions for {{ $user->full_name }}">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('users.show', $user) }}">
                                                    <i class="bi bi-eye me-2"></i>View
                                                </a>
                                            </li>
                                            @can('update', $user)
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('users.edit', $user) }}">
                                                        <i class="bi bi-pencil me-2"></i>Edit
                                                    </a>
                                                </li>
                                            @endcan
                                            {{-- The policy also hides this for yourself and for the last super admin. --}}
                                            @can('delete', $user)
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="{{ route('users.destroy', $user) }}"
                                                          data-confirm="Deactivate {{ $user->full_name }}? Their history is kept.">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="bi bi-person-dash me-2"></i>Deactivate
                                                        </button>
                                                    </form>
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

            @if ($users->hasPages())
                <div class="card-footer bg-white border-top">
                    {{ $users->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
@endsection
