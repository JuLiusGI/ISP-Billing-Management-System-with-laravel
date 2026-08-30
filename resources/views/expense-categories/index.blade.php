@extends('layouts.app')

@section('title', 'Expense categories')
@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('expenses.index') }}">Expenses</a></li>
    <li class="breadcrumb-item active" aria-current="page">Categories</li>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card border-0">
                <div class="card-header bg-white border-bottom fw-semibold text-navy">
                    Categories
                </div>

                <div class="table-responsive">
                    <table class="table table-app table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th>Entries</th>
                                <th class="text-end">Total spent</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td>
                                        <div class="fw-medium">{{ $category->name }}</div>
                                        <code class="small text-secondary">{{ $category->code }}</code>
                                        @if ($category->description)
                                            <div class="small text-secondary">{{ $category->description }}</div>
                                        @endif
                                    </td>
                                    <td class="small">{{ number_format($category->expenses_count) }}</td>
                                    <td class="text-end small">
                                        &#8369;{{ number_format((float) ($category->expenses_sum_amount ?? 0), 2) }}
                                    </td>
                                    <td>
                                        <span class="badge {{ $category->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $category->is_active ? 'Active' : 'Retired' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @can('update', $category)
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light border" type="button"
                                                        data-bs-toggle="dropdown" aria-expanded="false"
                                                        aria-label="Actions for {{ $category->name }}">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <li>
                                                        <button class="dropdown-item" type="button"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#edit-category-{{ $category->id }}">
                                                            <i class="bi bi-pencil me-2"></i>Edit
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <form method="POST" action="{{ route('expense-categories.update', $category) }}">
                                                            @csrf
                                                            @method('PUT')
                                                            <input type="hidden" name="name" value="{{ $category->name }}">
                                                            <input type="hidden" name="description" value="{{ $category->description }}">
                                                            <input type="hidden" name="is_active" value="{{ $category->is_active ? 0 : 1 }}">
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="bi bi-toggle-{{ $category->is_active ? 'off' : 'on' }} me-2"></i>
                                                                {{ $category->is_active ? 'Retire' : 'Reactivate' }}
                                                            </button>
                                                        </form>
                                                    </li>
                                                    @can('delete', $category)
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="POST" action="{{ route('expense-categories.destroy', $category) }}"
                                                                  data-confirm="Delete the {{ $category->name }} category?">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="dropdown-item text-danger">
                                                                    <i class="bi bi-trash me-2"></i>Delete
                                                                </button>
                                                            </form>
                                                        </li>
                                                    @endcan
                                                </ul>
                                            </div>
                                        @else
                                            <span class="small text-secondary">—</span>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="card-footer bg-white border-top small text-secondary">
                    A category that has expenses filed under it cannot be deleted. Retire it
                    instead: it disappears from new expenses while old ones keep their label.
                </div>
            </div>
        </div>

        @can('create', App\Models\ExpenseCategory::class)
            <div class="col-12 col-lg-4">
                <div class="card border-0">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">Add a category</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('expense-categories.store') }}" novalidate>
                            @csrf

                            <div class="mb-3">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name"
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                <div class="form-text">The code is derived from the name.</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <input type="text" name="description" id="description"
                                       class="form-control @error('description') is-invalid @enderror"
                                       value="{{ old('description') }}">
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Add category</button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    </div>

    {{-- Edit modals ----------------------------------------------------- --}}
    @can('create', App\Models\ExpenseCategory::class)
        @foreach ($categories as $category)
            <div class="modal fade" id="edit-category-{{ $category->id }}" tabindex="-1"
                 aria-labelledby="edit-category-label-{{ $category->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST"
                          action="{{ route('expense-categories.update', $category) }}">
                        @csrf
                        @method('PUT')

                        <div class="modal-header">
                            <h5 class="modal-title h6" id="edit-category-label-{{ $category->id }}">
                                Edit {{ $category->name }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="name-{{ $category->id }}" class="form-label">Name</label>
                                <input type="text" name="name" id="name-{{ $category->id }}"
                                       class="form-control" value="{{ $category->name }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="description-{{ $category->id }}" class="form-label">Description</label>
                                <input type="text" name="description" id="description-{{ $category->id }}"
                                       class="form-control" value="{{ $category->description }}">
                            </div>
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                       id="active-{{ $category->id }}" @checked($category->is_active)>
                                <label class="form-check-label small" for="active-{{ $category->id }}">
                                    Available for new expenses
                                </label>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endcan
@endsection
