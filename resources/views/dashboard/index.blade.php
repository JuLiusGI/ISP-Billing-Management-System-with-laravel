@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Total staff accounts', 'value' => $totalUsers, 'icon' => 'people-fill', 'accent' => 'primary'],
            ['label' => 'Active', 'value' => $activeUsers, 'icon' => 'person-check-fill', 'accent' => 'success'],
            ['label' => 'Suspended', 'value' => $suspendedUsers, 'icon' => 'person-slash', 'accent' => 'danger'],
        ] as $stat)
            <div class="col-12 col-sm-6 col-xl-4">
                <div class="card border-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="badge text-bg-{{ $stat['accent'] }} p-3 rounded-3">
                            <i class="bi bi-{{ $stat['icon'] }} fs-5"></i>
                        </span>
                        <div>
                            <div class="text-secondary small">{{ $stat['label'] }}</div>
                            <div class="fs-4 fw-bold text-navy lh-1">{{ number_format($stat['value']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0">
        <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
            <span class="fw-semibold text-navy">Recently added staff</span>
            @can('users.view')
                <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-primary">View all</a>
            @endcan
        </div>

        @if ($recentUsers->isEmpty())
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <p class="mb-0 mt-2">No staff accounts yet.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-app table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last sign-in</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentUsers as $user)
                            <tr>
                                <td>
                                    <div class="fw-medium">{{ $user->full_name }}</div>
                                    <div class="small text-secondary">{{ $user->email }}</div>
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
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <p class="text-secondary small mt-3 mb-0">
        Customer, billing and revenue analytics appear here as those modules are built.
    </p>
@endsection
