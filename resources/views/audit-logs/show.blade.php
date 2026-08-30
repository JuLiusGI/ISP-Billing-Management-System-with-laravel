@extends('layouts.app')

@section('title', 'Audit entry')
@section('breadcrumb')
    <li class="breadcrumb-item">Administration</li>
    <li class="breadcrumb-item"><a href="{{ route('audit-logs.index') }}">Audit logs</a></li>
    <li class="breadcrumb-item active" aria-current="page">Entry {{ $log->id }}</li>
@endsection

@php
    $render = function ($value) {
        if ($value === null) return '—';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_array($value)) return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return (string) $value === '' ? '(empty)' : (string) $value;
    };
@endphp

@section('content')
    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card border-0">
                <div class="card-header bg-white border-bottom fw-semibold text-navy">Event</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-secondary fw-normal">When</dt>
                        <dd class="col-7">{{ $log->created_at?->format('d M Y, g:i:s A') ?? '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">User</dt>
                        <dd class="col-7">
                            @if ($log->user)
                                {{ $log->user->full_name }}
                                <div class="text-secondary">{{ $log->user->email }}</div>
                            @else
                                System
                            @endif
                        </dd>

                        <dt class="col-5 text-secondary fw-normal">Module</dt>
                        <dd class="col-7">{{ $log->module }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Action</dt>
                        <dd class="col-7"><code>{{ $log->action }}</code></dd>

                        <dt class="col-5 text-secondary fw-normal">Record</dt>
                        <dd class="col-7">
                            @if ($log->auditable_type)
                                <code>{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</code>
                            @else
                                —
                            @endif
                        </dd>

                        <dt class="col-5 text-secondary fw-normal">IP address</dt>
                        <dd class="col-7">{{ $log->ip_address ?: 'console' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">User agent</dt>
                        <dd class="col-7 mb-0" style="word-break: break-word;">
                            <span class="text-secondary">{{ $log->user_agent ?: '—' }}</span>
                        </dd>
                    </dl>

                    @if ($log->description)
                        <hr>
                        <div class="small">
                            <div class="text-secondary mb-1">Description</div>
                            {{ $log->description }}
                        </div>
                    @endif
                </div>
                <div class="card-footer bg-white border-top">
                    <a href="{{ route('audit-logs.index') }}" class="btn btn-sm btn-light border">
                        <i class="bi bi-arrow-left me-1"></i> Back to the trail
                    </a>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card border-0 h-100">
                <div class="card-header bg-white border-bottom fw-semibold text-navy">
                    What changed
                </div>

                @if (empty($changes))
                    <div class="empty-state">
                        <i class="bi bi-dash-circle"></i>
                        <p class="mb-0 mt-2">
                            This event records no field values — the action itself is the record.
                        </p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-app table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>Field</th><th>Before</th><th>After</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($changes as $change)
                                    <tr>
                                        <td class="small"><code>{{ $change['field'] }}</code></td>
                                        <td class="small text-secondary" style="word-break: break-word;">
                                            {{ $render($change['old']) }}
                                        </td>
                                        <td class="small fw-medium" style="word-break: break-word;">
                                            {{ $render($change['new']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
