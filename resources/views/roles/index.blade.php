@extends('layouts.app')

@section('title', 'Roles & permissions')
@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Roles &amp; permissions</li>
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h5 mb-0 text-navy">Roles</h2>
            <p class="small text-secondary mb-0">
                Abilities are enforced on every route, not merely hidden from the menu.
            </p>
        </div>

        @can('create', App\Models\Role::class)
            <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Add role
            </a>
        @endcan
    </div>

    <div class="card border-0">
        <div class="table-responsive">
            <table class="table table-app table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Role</th>
                        <th>Abilities</th>
                        <th>Users</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td>
                                <div class="fw-medium">
                                    {{ $role->display_name }}
                                    @if ($role->is_system)
                                        <span class="badge text-bg-light border fw-normal ms-1">System</span>
                                    @endif
                                </div>
                                <div class="small text-secondary">{{ $role->description }}</div>
                                <code class="small">{{ $role->name }}</code>
                            </td>
                            <td>
                                @if ($role->name === App\Models\Role::SUPER_ADMIN)
                                    <span class="badge text-bg-danger">Unrestricted</span>
                                @else
                                    <span class="badge text-bg-light border">{{ $role->permissions_count }} granted</span>
                                @endif
                            </td>
                            <td class="small">{{ $role->users_count }}</td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light border" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false"
                                            aria-label="Actions for {{ $role->display_name }}">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                        @can('update', $role)
                                            <li>
                                                <a class="dropdown-item" href="{{ route('roles.edit', $role) }}">
                                                    <i class="bi bi-pencil me-2"></i>Edit abilities
                                                </a>
                                            </li>
                                        @else
                                            <li>
                                                <span class="dropdown-item-text small text-secondary"
                                                      style="max-width: 15rem; white-space: normal;">
                                                    @if ($role->name === App\Models\Role::SUPER_ADMIN)
                                                        Super Admin bypasses every check, so there is nothing to edit.
                                                    @else
                                                        You cannot edit this role.
                                                    @endif
                                                </span>
                                            </li>
                                        @endcan

                                        @can('delete', $role)
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="{{ route('roles.destroy', $role) }}"
                                                      data-confirm="Delete the {{ $role->display_name }} role?">
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
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <p class="small text-secondary mt-3 mb-0">
        System roles cannot be deleted, and a role still assigned to someone must be emptied first.
    </p>
@endsection
