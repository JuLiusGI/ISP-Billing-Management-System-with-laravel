@php
    /** @var \App\Models\Role|null $role */
    $editing = isset($role);
    $granted = array_map('intval', (array) old('permissions', $editing ? $role->permissions->pluck('id')->all() : []));
@endphp

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label for="display_name" class="form-label">Role name <span class="text-danger">*</span></label>
        <input type="text" name="display_name" id="display_name"
               class="form-control @error('display_name') is-invalid @enderror"
               value="{{ old('display_name', $role->display_name ?? '') }}" required>
        @error('display_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        @if ($editing)
            <div class="form-text">
                Identifier <code>{{ $role->name }}</code> is fixed; code and seeders refer to it.
            </div>
        @endif
    </div>

    <div class="col-md-6">
        <label for="description" class="form-label">Description</label>
        <input type="text" name="description" id="description"
               class="form-control @error('description') is-invalid @enderror"
               value="{{ old('description', $role->description ?? '') }}">
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-2">
    <span class="fw-semibold text-navy">Abilities <span class="text-danger">*</span></span>
    <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="toggle-all">
        <label class="form-check-label small" for="toggle-all">Select all</label>
    </div>
</div>

@error('permissions')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

<div class="row g-3">
    @foreach ($permissionsByModule as $module => $permissions)
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                    <span class="small fw-semibold">{{ $module }}</span>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input module-toggle" type="checkbox"
                               data-module="{{ Str::slug($module) }}"
                               aria-label="Select all {{ $module }} abilities">
                    </div>
                </div>
                <div class="card-body py-2">
                    @foreach ($permissions as $permission)
                        <div class="form-check">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                   id="permission-{{ $permission->id }}"
                                   class="form-check-input permission-check"
                                   data-module="{{ Str::slug($module) }}"
                                   @checked(in_array($permission->id, $granted, true))>
                            <label for="permission-{{ $permission->id }}" class="form-check-label small">
                                {{ $permission->display_name }}
                                <code class="d-block text-secondary">{{ $permission->name }}</code>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>

@push('scripts')
<script>
    document.getElementById('toggle-all')?.addEventListener('change', (event) => {
        document.querySelectorAll('.permission-check, .module-toggle').forEach((box) => {
            box.checked = event.target.checked;
        });
    });

    document.querySelectorAll('.module-toggle').forEach((toggle) => {
        toggle.addEventListener('change', (event) => {
            const selector = '.permission-check[data-module="' + event.target.dataset.module + '"]';
            document.querySelectorAll(selector).forEach((box) => {
                box.checked = event.target.checked;
            });
        });
    });
</script>
@endpush
