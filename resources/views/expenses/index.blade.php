@extends('layouts.app')

@section('title', request()->boolean('archived') ? 'Archived expenses' : 'Expenses')
@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active" aria-current="page">Expenses</li>
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h5 mb-0 text-navy">
                {{ request()->boolean('archived') ? 'Archived expenses' : 'Expenses' }}
            </h2>
            <p class="small text-secondary mb-0">{{ number_format($expenses->total()) }} entries</p>
        </div>

        <div class="d-flex gap-2">
            @if (request()->boolean('archived'))
                <a href="{{ route('expenses.index') }}" class="btn btn-sm btn-light border">
                    <i class="bi bi-arrow-left me-1"></i> Active list
                </a>
            @else
                <a href="{{ route('expenses.index', ['archived' => 1]) }}" class="btn btn-sm btn-light border">
                    <i class="bi bi-archive me-1"></i> Archived
                </a>
            @endif

            @can('create', App\Models\Expense::class)
                <a href="{{ route('expenses.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Record expense
                </a>
            @endcan
        </div>
    </div>

    {{-- Summary for the filtered set, not just this page ------------------ --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-4">
            <div class="card border-0 h-100">
                <div class="card-body">
                    <div class="text-secondary small">Total for this selection</div>
                    <div class="fs-4 fw-bold text-navy">
                        &#8369;{{ number_format((float) $total, 2) }}
                    </div>
                    <div class="small text-secondary">
                        {{ request()->hasAny(['from', 'to', 'category', 'method', 'search'])
                            ? 'Matching the filters below'
                            : 'All recorded expenses' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card border-0 h-100">
                <div class="card-body">
                    <div class="text-secondary small mb-2">By category</div>
                    @if ($byCategory->isEmpty())
                        <p class="small text-secondary mb-0">Nothing to summarise.</p>
                    @else
                        @foreach ($byCategory as $row)
                            @php
                                $share = (float) $total > 0 ? ((float) $row->total / (float) $total) * 100 : 0;
                            @endphp
                            <div class="d-flex justify-content-between align-items-center small">
                                <span>{{ $row->name }}</span>
                                <span class="text-secondary">
                                    &#8369;{{ number_format((float) $row->total, 2) }}
                                    <span class="ms-1">({{ number_format($share, 1) }}%)</span>
                                </span>
                            </div>
                            <div class="progress mb-2" style="height: 4px;" role="progressbar"
                                 aria-label="{{ $row->name }} share" aria-valuenow="{{ round($share) }}"
                                 aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-navy" style="width: {{ $share }}%"></div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Filters ---------------------------------------------------------- --}}
    <div class="card border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('expenses.index') }}" class="row g-2 align-items-end">
                @if (request()->boolean('archived'))
                    <input type="hidden" name="archived" value="1">
                @endif

                <div class="col-12 col-lg-3">
                    <label for="search" class="form-label small">Search</label>
                    <input type="search" name="search" id="search" class="form-control form-control-sm"
                           value="{{ request('search') }}" placeholder="Reference, description or vendor">
                </div>

                <div class="col-6 col-lg-2">
                    <label for="category" class="form-label small">Category</label>
                    <select name="category" id="category" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category') == $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label for="method" class="form-label small">Paid by</label>
                    <select name="method" id="method" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($methods as $method)
                            <option value="{{ $method->value }}" @selected(request('method') === $method->value)>
                                {{ $method->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label for="from" class="form-label small">From</label>
                    <input type="date" name="from" id="from" class="form-control form-control-sm"
                           value="{{ request('from') }}">
                </div>

                <div class="col-6 col-lg-2">
                    <label for="to" class="form-label small">To</label>
                    <input type="date" name="to" id="to" class="form-control form-control-sm"
                           value="{{ request('to') }}">
                </div>

                <div class="col-12 col-lg-1 d-grid">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Listing ---------------------------------------------------------- --}}
    <div class="card border-0">
        @if ($expenses->isEmpty())
            <div class="empty-state">
                <i class="bi bi-wallet2"></i>
                <p class="mb-1 mt-2">No expenses match these filters.</p>
                <a href="{{ route('expenses.index') }}" class="small">Clear filters</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-app table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Vendor</th>
                            <th>Paid by</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($expenses as $expense)
                            <tr>
                                <td>
                                    <a href="{{ route('expenses.show', $expense) }}" class="text-decoration-none">
                                        <code class="small">{{ $expense->expense_reference }}</code>
                                    </a>
                                </td>
                                <td class="small text-nowrap">{{ $expense->expense_date->format('d M Y') }}</td>
                                <td class="small">{{ $expense->description }}</td>
                                <td class="small">
                                    <span class="badge text-bg-light border fw-normal">
                                        {{ $expense->category->name }}
                                    </span>
                                </td>
                                <td class="small text-secondary">{{ $expense->vendor ?: '—' }}</td>
                                <td class="small text-secondary">{{ $expense->payment_method->label() }}</td>
                                <td class="text-end fw-medium">
                                    &#8369;{{ number_format((float) $expense->amount, 2) }}
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border" type="button"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                aria-label="Actions for {{ $expense->expense_reference }}">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('expenses.show', $expense) }}">
                                                    <i class="bi bi-eye me-2"></i>View
                                                </a>
                                            </li>
                                            @can('update', $expense)
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('expenses.edit', $expense) }}">
                                                        <i class="bi bi-pencil me-2"></i>Edit
                                                    </a>
                                                </li>
                                            @endcan
                                            @can('delete', $expense)
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="{{ route('expenses.destroy', $expense) }}"
                                                          data-confirm="Archive {{ $expense->expense_reference }}?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="bi bi-archive me-2"></i>Archive
                                                        </button>
                                                    </form>
                                                </li>
                                            @endcan
                                            @can('restore', $expense)
                                                <li>
                                                    <form method="POST" action="{{ route('expenses.restore', $expense->id) }}">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="bi bi-arrow-counterclockwise me-2"></i>Restore
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

            @if ($expenses->hasPages())
                <div class="card-footer bg-white border-top">
                    {{ $expenses->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
@endsection
