@php
    /** @var \App\Models\InternetPlan|null $plan */
    // Undefined-variable warnings are exceptions here, so bind it explicitly.
    $plan = $plan ?? null;
    $editing = $plan !== null;
@endphp

@if ($editing && $plan->subscriptions_count > 0)
    <div class="alert alert-info d-flex gap-2 align-items-start" role="alert">
        <i class="bi bi-info-circle mt-1"></i>
        <div class="small">
            <strong>{{ $plan->subscriptions_count }} subscription(s) use this plan.</strong>
            Changing the price here applies to new signups only — existing subscriptions keep the
            rate they were created with, and issued invoices are never rewritten.
        </div>
    </div>
@endif

<div class="row g-3">
    <div class="col-md-3">
        <label for="plan_code" class="form-label">Plan code <span class="text-danger">*</span></label>
        <input type="text" name="plan_code" id="plan_code"
               class="form-control @error('plan_code') is-invalid @enderror"
               value="{{ old('plan_code', $plan->plan_code ?? '') }}"
               placeholder="HOME-50" required>
        @error('plan_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Letters, numbers and hyphens. Stored uppercase.</div>
    </div>

    <div class="col-md-5">
        <label for="name" class="form-label">Plan name <span class="text-danger">*</span></label>
        <input type="text" name="name" id="name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $plan->name ?? '') }}" placeholder="Home 50 Mbps" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="billing_cycle" class="form-label">Billing cycle <span class="text-danger">*</span></label>
        <select name="billing_cycle" id="billing_cycle"
                class="form-select @error('billing_cycle') is-invalid @enderror" required>
            @foreach ($cycles as $cycle)
                <option value="{{ $cycle->value }}"
                    @selected(old('billing_cycle', $plan->billing_cycle->value ?? 'monthly') === $cycle->value)>
                    {{ $cycle->label() }}
                </option>
            @endforeach
        </select>
        @error('billing_cycle')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="download_speed" class="form-label">Download speed <span class="text-danger">*</span></label>
        <input type="number" name="download_speed" id="download_speed" min="1" step="1"
               class="form-control @error('download_speed') is-invalid @enderror"
               value="{{ old('download_speed', $plan->download_speed ?? '') }}" required>
        @error('download_speed')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="upload_speed" class="form-label">Upload speed <span class="text-danger">*</span></label>
        <input type="number" name="upload_speed" id="upload_speed" min="1" step="1"
               class="form-control @error('upload_speed') is-invalid @enderror"
               value="{{ old('upload_speed', $plan->upload_speed ?? '') }}" required>
        @error('upload_speed')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="speed_unit" class="form-label">Speed unit <span class="text-danger">*</span></label>
        <select name="speed_unit" id="speed_unit"
                class="form-select @error('speed_unit') is-invalid @enderror" required>
            @foreach ($speedUnits as $unit)
                <option value="{{ $unit->value }}"
                    @selected(old('speed_unit', $plan->speed_unit->value ?? 'Mbps') === $unit->value)>
                    {{ $unit->label() }}
                </option>
            @endforeach
        </select>
        @error('speed_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <span class="form-label d-block">Availability <span class="text-danger">*</span></span>
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                   @checked(old('is_active', $plan->is_active ?? true))>
            <label class="form-check-label" for="is_active">Available for new signups</label>
        </div>
        @error('is_active')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="monthly_price" class="form-label">Monthly price <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">&#8369;</span>
            <input type="number" name="monthly_price" id="monthly_price" min="0" step="0.01"
                   class="form-control @error('monthly_price') is-invalid @enderror"
                   value="{{ old('monthly_price', $plan->monthly_price ?? '') }}" required>
            @error('monthly_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-4">
        <label for="installation_fee" class="form-label">Installation fee <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">&#8369;</span>
            <input type="number" name="installation_fee" id="installation_fee" min="0" step="0.01"
                   class="form-control @error('installation_fee') is-invalid @enderror"
                   value="{{ old('installation_fee', $plan->installation_fee ?? '0.00') }}" required>
            @error('installation_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-4">
        <label for="activation_fee" class="form-label">Activation fee <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">&#8369;</span>
            <input type="number" name="activation_fee" id="activation_fee" min="0" step="0.01"
                   class="form-control @error('activation_fee') is-invalid @enderror"
                   value="{{ old('activation_fee', $plan->activation_fee ?? '0.00') }}" required>
            @error('activation_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea name="description" id="description" rows="3"
                  class="form-control @error('description') is-invalid @enderror"
                  placeholder="What this plan includes, fair-use notes, and so on.">{{ old('description', $plan->description ?? '') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
