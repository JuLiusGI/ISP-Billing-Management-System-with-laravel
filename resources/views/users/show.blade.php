@extends('layouts.app')

@section('title', $user->full_name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $user->full_name }}</li>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="app-avatar" style="width:3rem;height:3rem;font-size:1rem;">
                            {{ $user->initials }}
                        </span>
                        <div>
                            <h2 class="h5 mb-0 text-navy">{{ $user->full_name }}</h2>
                            <span class="badge {{ $user->status->badgeClass() }}">{{ $user->status->label() }}</span>
                        </div>
                    </div>

                    <dl class="row mb-0 small">
                        <dt class="col-5 text-secondary fw-normal">Email</dt>
                        <dd class="col-7">{{ $user->email }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Contact</dt>
                        <dd class="col-7">{{ $user->phone ?: '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Last sign-in</dt>
                        <dd class="col-7">{{ $user->last_login_at?->format('d M Y, g:i A') ?? 'Never' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">From address</dt>
                        <dd class="col-7">{{ $user->last_login_ip ?? '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Added</dt>
                        <dd class="col-7 mb-0">{{ $user->created_at->format('d M Y') }}</dd>
                    </dl>
                </div>

                @can('update', $user)
                    <div class="card-footer bg-white border-top">
                        <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-primary">
                            <i class="bi bi-pencil me-1"></i> Edit user
                        </a>
                    </div>
                @endcan
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card border-0">
                <div class="card-header bg-white border-bottom fw-semibold text-navy">
                    Roles and abilities
                </div>
                <div class="card-body">
                    @forelse ($user->roles as $role)
                        <div class="mb-3">
                            <div class="fw-medium">{{ $role->display_name }}</div>
                            <p class="small text-secondary mb-2">{{ $role->description }}</p>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach ($role->permissions->sortBy('name') as $permission)
                                    <span class="badge text-bg-light border fw-normal">{{ $permission->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-secondary small mb-0">
                            This account has no roles, so it can reach nothing beyond the dashboard.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
