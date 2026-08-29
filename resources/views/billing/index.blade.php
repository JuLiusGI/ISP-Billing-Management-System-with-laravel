@extends('layouts.app')

@section('title', 'Billing cycles')
@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Billing cycles</li>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card border-0">
                <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
                    <span class="fw-semibold text-navy">Billing cycles</span>
                    <span class="small text-secondary">{{ number_format($cycles->total()) }} recorded</span>
                </div>

                @if ($cycles->isEmpty())
                    <div class="empty-state">
                        <i class="bi bi-calendar3"></i>
                        <p class="mb-0 mt-2">No billing cycle has been opened yet.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-app table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Period</th>
                                    <th>Due</th>
                                    <th>Invoices</th>
                                    <th class="text-end">Invoiced</th>
                                    <th class="text-end">Outstanding</th>
                                    <th>Status</th>
                                    <th class="text-end"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cycles as $cycle)
                                    <tr>
                                        <td>
                                            <div class="fw-medium">{{ $cycle->name }}</div>
                                            <div class="small text-secondary">
                                                {{ $cycle->period_start->format('d M') }} –
                                                {{ $cycle->period_end->format('d M Y') }}
                                            </div>
                                        </td>
                                        <td class="small">{{ $cycle->due_date->format('d M Y') }}</td>
                                        <td class="small">{{ number_format($cycle->invoices_count) }}</td>
                                        <td class="text-end small">
                                            &#8369;{{ number_format((float) $cycle->invoiced_total, 2) }}
                                        </td>
                                        <td class="text-end small">
                                            &#8369;{{ number_format((float) $cycle->outstanding_total, 2) }}
                                        </td>
                                        <td>
                                            <span class="badge {{ $cycle->status->badgeClass() }}">
                                                {{ $cycle->status->label() }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('billing.show', $cycle) }}"
                                               class="btn btn-sm btn-light border">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($cycles->hasPages())
                        <div class="card-footer bg-white border-top">
                            {{ $cycles->links('pagination::bootstrap-5') }}
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <div class="col-12 col-lg-4">
            @can('billing.generate')
                <div class="card border-0 mb-3">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">Open a billing cycle</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('billing.store') }}">
                            @csrf
                            <label for="month" class="form-label small">Month to bill</label>
                            <input type="month" name="month" id="month"
                                   class="form-control @error('month') is-invalid @enderror"
                                   value="{{ old('month', $suggestedMonth->format('Y-m')) }}" required>
                            @error('month')<div class="invalid-feedback">{{ $message }}</div>@enderror

                            <button type="submit" class="btn btn-primary w-100 mt-3">
                                <i class="bi bi-calendar-plus me-1"></i> Open cycle
                            </button>
                        </form>
                        <p class="form-text mb-0 mt-2">
                            Opening a month that already exists simply reopens it; nothing is duplicated.
                        </p>
                    </div>
                </div>

                <div class="card border-0">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">Maintenance</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('billing.mark-overdue') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                <i class="bi bi-exclamation-triangle me-1"></i> Mark overdue invoices
                            </button>
                        </form>
                        <p class="form-text mb-0 mt-2">
                            Moves unpaid and partly paid invoices past their due date to Overdue.
                            Safe to run at any time.
                        </p>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
