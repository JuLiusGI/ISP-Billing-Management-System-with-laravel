@extends('layouts.app')

@section('title', 'Audit logs')
@section('breadcrumb')
    <li class="breadcrumb-item">Administration</li>
    <li class="breadcrumb-item active" aria-current="page">Audit logs</li>
@endsection

@php
    $actionBadge = fn (string $action) => match (true) {
        str_contains($action, 'failed'), str_contains($action, 'throttled') => 'text-bg-danger',
        str_contains($action, 'deleted'), str_contains($action, 'archived') => 'text-bg-warning',
        $action === 'created' => 'text-bg-success',
        $action === 'login' => 'text-bg-primary',
        default => 'text-bg-light border',
    };
@endphp

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-start justify-content-between mb-3">
        <div>
            <h2 class="h5 mb-0 text-navy">Audit logs</h2>
            <p class="small text-secondary mb-0">
                {{ number_format($logs->total()) }} recorded events. The trail is read-only.
            </p>
        </div>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('audit-logs.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-lg-3">
                    <label for="search" class="form-label small">Search</label>
                    <input type="search" name="search" id="search" class="form-control form-control-sm"
                           value="{{ request('search') }}" placeholder="Description or IP address">
                </div>

                <div class="col-6 col-lg-2">
                    <label for="module" class="form-label small">Module</label>
                    <select name="module" id="module" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label for="action" class="form-label small">Action</label>
                    <select name="action" id="action" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>
                                {{ str_replace('_', ' ', $action) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label for="user" class="form-label small">User</label>
                    <select name="user" id="user" class="form-select form-select-sm">
                        <option value="">Anyone</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(request('user') == $user->id)>
                                {{ $user->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-1">
                    <label for="from" class="form-label small">From</label>
                    <input type="date" name="from" id="from" class="form-control form-control-sm"
                           value="{{ request('from') }}">
                </div>

                <div class="col-6 col-lg-1">
                    <label for="to" class="form-label small">To</label>
                    <input type="date" name="to" id="to" class="form-control form-control-sm"
                           value="{{ request('to') }}">
                </div>

                <div class="col-6 col-lg-1 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('audit-logs.index') }}" class="btn btn-sm btn-light border">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0">
        @if ($logs->isEmpty())
            <div class="empty-state">
                <i class="bi bi-journal-text"></i>
                <p class="mb-1 mt-2">No events match these filters.</p>
                <a href="{{ route('audit-logs.index') }}" class="small">Clear filters</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-app table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>When</th>
                            <th>User</th>
                            <th>Module</th>
                            <th>Action</th>
                            <th>Detail</th>
                            <th>From</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="small text-nowrap">
                                    {{ $log->created_at?->format('d M Y') }}
                                    <div class="text-secondary">{{ $log->created_at?->format('g:i:s A') }}</div>
                                </td>
                                <td class="small">
                                    {{ $log->user?->full_name ?? 'System' }}
                                </td>
                                <td class="small">
                                    <span class="badge text-bg-light border fw-normal">{{ $log->module }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $actionBadge($log->action) }}">
                                        {{ str_replace('_', ' ', $log->action) }}
                                    </span>
                                </td>
                                <td class="small">{{ $log->description ?: '—' }}</td>
                                <td class="small text-secondary">{{ $log->ip_address ?: 'console' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('audit-logs.show', $log) }}"
                                       class="btn btn-sm btn-light border"
                                       aria-label="View audit entry {{ $log->id }}">
                                        <i class="bi bi-eye"></i>
                                    </a>
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
