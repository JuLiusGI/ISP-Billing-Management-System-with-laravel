@extends('layouts.app')

@section('title', $expense->expense_reference)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('expenses.index') }}">Expenses</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $expense->expense_reference }}</li>
@endsection

@section('content')
    @if ($expense->trashed())
        <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-archive"></i>
            <div>This expense is archived and is excluded from expense totals.</div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card border-0">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-navy">
                        <code>{{ $expense->expense_reference }}</code>
                    </span>
                    <span class="fs-5 fw-bold text-navy">
                        &#8369;{{ number_format((float) $expense->amount, 2) }}
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-4 text-secondary fw-normal">Description</dt>
                        <dd class="col-8">{{ $expense->description }}</dd>

                        <dt class="col-4 text-secondary fw-normal">Category</dt>
                        <dd class="col-8">
                            <span class="badge text-bg-light border fw-normal">{{ $expense->category->name }}</span>
                            @unless ($expense->category->is_active)
                                <span class="badge text-bg-secondary">retired</span>
                            @endunless
                        </dd>

                        <dt class="col-4 text-secondary fw-normal">Date</dt>
                        <dd class="col-8">{{ $expense->expense_date->format('d M Y') }}</dd>

                        <dt class="col-4 text-secondary fw-normal">Paid by</dt>
                        <dd class="col-8">{{ $expense->payment_method->label() }}</dd>

                        <dt class="col-4 text-secondary fw-normal">Vendor</dt>
                        <dd class="col-8">{{ $expense->vendor ?: '—' }}</dd>

                        <dt class="col-4 text-secondary fw-normal">Recorded by</dt>
                        <dd class="col-8">{{ $expense->createdBy?->full_name ?? '—' }}</dd>

                        <dt class="col-4 text-secondary fw-normal">Recorded on</dt>
                        <dd class="col-8 mb-0">{{ $expense->created_at->format('d M Y, g:i A') }}</dd>
                    </dl>

                    @if ($expense->notes)
                        <hr>
                        <div class="small">
                            <div class="text-secondary mb-1">Notes</div>
                            <p class="mb-0" style="white-space: pre-line;">{{ $expense->notes }}</p>
                        </div>
                    @endif
                </div>

                <div class="card-footer bg-white border-top d-flex gap-2">
                    @can('update', $expense)
                        <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-sm btn-primary">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </a>
                    @endcan
                    @can('delete', $expense)
                        <form method="POST" action="{{ route('expenses.destroy', $expense) }}"
                              data-confirm="Archive {{ $expense->expense_reference }}?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-light border text-danger">
                                <i class="bi bi-archive me-1"></i> Archive
                            </button>
                        </form>
                    @endcan
                    @can('restore', $expense)
                        <form method="POST" action="{{ route('expenses.restore', $expense->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-light border">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                            </button>
                        </form>
                    @endcan
                    <a href="{{ route('expenses.index') }}" class="btn btn-sm btn-light border ms-auto">Back</a>
                </div>
            </div>
        </div>
    </div>
@endsection
