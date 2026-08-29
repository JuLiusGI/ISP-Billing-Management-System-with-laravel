@php
    /** @var \App\Models\User|null $user */
    $editing = isset($user);
    $selectedRoles = old('roles', $editing ? $user->roles->pluck('id')->all() : []);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="first_name" class="form-label">First name <span class="text-danger">*</span></label>
        <input type="text" name="first_name" id="first_name"
               class="form-control @error('first_name') is-invalid @enderror"
               value="{{ old('first_name', $user->first_name ?? '') }}" required>
        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="last_name" class="form-label">Last name <span class="text-danger">*</span></label>
        <input type="text" name="last_name" id="last_name"
               class="form-control @error('last_name') is-invalid @enderror"
               value="{{ old('last_name', $user->last_name ?? '') }}" required>
        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email address <span class="text-danger">*</span></label>
        <input type="email" name="email" id="email"
               class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $user->email ?? '') }}" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="phone" class="form-label">Contact number</label>
        <input type="text" name="phone" id="phone"
               class="form-control @error('phone') is-invalid @enderror"
               value="{{ old('phone', $user->phone ?? '') }}">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="password" class="form-label">
            Password @unless($editing)<span class="text-danger">*</span>@endunless
        </label>
        <input type="password" name="password" id="password"
               class="form-control @error('password') is-invalid @enderror"
               autocomplete="new-password" @unless($editing) required @endunless>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if ($editing)
            <div class="form-text">Leave blank to keep the current password.</div>
        @endif
    </div>

    <div class="col-md-6">
        <label for="password_confirmation" class="form-label">Confirm password</label>
        <input type="password" name="password_confirmation" id="password_confirmation"
               class="form-control" autocomplete="new-password" @unless($editing) required @endunless>
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label">Account status <span class="text-danger">*</span></label>
        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}"
                    @selected(old('status', $user->status->value ?? 'active') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Only active accounts can sign in.</div>
    </div>

    <div class="col-md-6">
        <span class="form-label d-block">Roles <span class="text-danger">*</span></span>
        <div class="border rounded p-2 @error('roles') border-danger @enderror">
            @foreach ($roles as $role)
                <div class="form-check">
                    <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                           id="role-{{ $role->id }}" class="form-check-input"
                           @checked(in_array($role->id, array_map('intval', (array) $selectedRoles), true))>
                    <label for="role-{{ $role->id }}" class="form-check-label">
                        {{ $role->display_name }}
                        <span class="d-block small text-secondary">{{ $role->description }}</span>
                    </label>
                </div>
            @endforeach
        </div>
        @error('roles')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
</div>
