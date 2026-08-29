@extends('layouts.app')

@section('title', $subscription->subscription_code)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('subscriptions.index') }}">Subscriptions</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $subscription->subscription_code }}</li>
@endsection

@section('content')
    <div class="card border-0 mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center">
            <div class="flex-grow-1">
                <h2 class="h5 mb-1 text-navy">
                    {{ $subscription->internetPlan->name }}
                    <span class="badge {{ $subscription->status->badgeClass() }} align-middle">
                        {{ $subscription->status->label() }}
                    </span>
                </h2>
                <div class="small text-secondary">
                    <code>{{ $subscription->subscription_code }}</code>
                    &middot;
                    <a href="{{ route('customers.show', $subscription->customer) }}" class="text-decoration-none">
                        {{ $subscription->customer->full_name }} ({{ $subscription->customer->account_number }})
                    </a>
                </div>
            </div>

            @can('update', $subscription)
                <a href="{{ route('subscriptions.edit', $subscription) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card border-0 mb-3">
                <div class="card-header bg-white border-bottom fw-semibold text-navy">Service details</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 col-md-4 text-secondary fw-normal">Plan</dt>
                        <dd class="col-7 col-md-8">
                            {{ $subscription->internetPlan->name }}
                            <span class="text-secondary">({{ $subscription->internetPlan->speed_label }})</span>
                        </dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Agreed monthly rate</dt>
                        <dd class="col-7 col-md-8">&#8369;{{ number_format((float) $subscription->monthly_rate, 2) }}</dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Discount</dt>
                        <dd class="col-7 col-md-8">&#8369;{{ number_format((float) $subscription->discount_amount, 2) }}</dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Billed each month</dt>
                        <dd class="col-7 col-md-8 fw-medium">
                            &#8369;{{ number_format((float) $subscription->net_monthly_rate, 2) }}
                        </dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Installation fee</dt>
                        <dd class="col-7 col-md-8">&#8369;{{ number_format((float) $subscription->installation_fee, 2) }}</dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Billing day</dt>
                        <dd class="col-7 col-md-8">{{ $subscription->billing_day }}</dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Start date</dt>
                        <dd class="col-7 col-md-8">{{ $subscription->start_date->format('d M Y') }}</dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Activated</dt>
                        <dd class="col-7 col-md-8">{{ $subscription->activation_date?->format('d M Y') ?? '—' }}</dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Expires</dt>
                        <dd class="col-7 col-md-8">
                            {{ $subscription->expiration_date?->format('d M Y') ?? 'Open-ended' }}
                            @if ($subscription->isExpired())
                                <span class="badge text-bg-warning">Past expiry</span>
                            @endif
                        </dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Connection</dt>
                        <dd class="col-7 col-md-8">{{ $subscription->connection_type->label() }}</dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Username</dt>
                        <dd class="col-7 col-md-8">{{ $subscription->username ?: '—' }}</dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Static IP</dt>
                        <dd class="col-7 col-md-8">{{ $subscription->static_ip ?: '—' }}</dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Notes</dt>
                        <dd class="col-7 col-md-8 mb-0" style="white-space: pre-line;">
                            {{ $subscription->service_notes ?: '—' }}
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card border-0">
                <div class="card-header bg-white border-bottom fw-semibold text-navy">Service history</div>
                <div class="card-body">
                    @forelse ($subscription->serviceStatusLogs->sortByDesc('created_at') as $log)
                        <div class="d-flex gap-3 {{ $loop->last ? '' : 'border-bottom' }} py-2 small">
                            <div class="text-secondary" style="min-width:9.5rem;">
                                {{ $log->created_at->format('d M Y, g:i A') }}
                            </div>
                            <div>
                                <span class="fw-medium">
                                    {{ $log->from_status ? ucfirst(str_replace('_', ' ', $log->from_status)) : 'Created' }}
                                    &rarr;
                                    {{ ucfirst(str_replace('_', ' ', $log->to_status)) }}
                                </span>
                                @if ($log->reason)
                                    <div class="text-secondary">{{ $log->reason }}</div>
                                @endif
                                <div class="text-secondary">
                                    {{ $log->is_automatic ? 'Automatic' : ($log->changedBy?->full_name ?? 'System') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="small text-secondary mb-0">No status changes recorded.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card border-0">
                <div class="card-header bg-white border-bottom fw-semibold text-navy">Service status</div>
                <div class="card-body">
                    @php $transitions = $subscription->status->allowedTransitions(); @endphp

                    @can('manageStatus', $subscription)
                        @if (empty($transitions))
                            <p class="small text-secondary mb-0">
                                This subscription is {{ strtolower($subscription->status->label()) }} and cannot
                                be moved any further.
                            </p>
                        @else
                            <form method="POST" action="{{ route('subscriptions.status', $subscription) }}">
                                @csrf
                                @method('PATCH')

                                <div class="mb-3">
                                    <label for="reason" class="form-label small">Reason</label>
                                    <input type="text" name="reason" id="reason" maxlength="255"
                                           class="form-control form-control-sm @error('reason') is-invalid @enderror"
                                           placeholder="Recorded on the service history">
                                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="d-grid gap-2">
                                    @foreach ($transitions as $target)
                                        <button type="submit" name="status" value="{{ $target->value }}"
                                                class="btn btn-sm {{ $target->value === 'cancelled' ? 'btn-outline-danger' : 'btn-outline-primary' }}"
                                                @if ($target->value === 'cancelled')
                                                    data-confirm-status="Cancel this subscription? This cannot be undone."
                                                @endif>
                                            <i class="bi bi-{{ $target->icon() }} me-1"></i>
                                            {{ $target->actionLabel() }}
                                        </button>
                                    @endforeach
                                </div>
                            </form>
                        @endif
                    @else
                        <p class="small text-secondary mb-0">
                            You do not have permission to change service status.
                        </p>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.querySelectorAll('[data-confirm-status]').forEach((button) => {
            button.addEventListener('click', (event) => {
                if (!window.confirm(button.dataset.confirmStatus)) {
                    event.preventDefault();
                }
            });
        });
    </script>
    @endpush
@endsection
