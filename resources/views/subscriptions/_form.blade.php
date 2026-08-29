@php
    /** @var \App\Models\Subscription|null $subscription */
    $subscription = $subscription ?? null;
    $selectedCustomer = $selectedCustomer ?? null;
    $editing = $subscription !== null;

    $currentCustomerId = old('customer_id', $subscription->customer_id ?? $selectedCustomer?->id);
    $currentPlanId = old('internet_plan_id', $subscription->internet_plan_id ?? null);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="customer_id" class="form-label">Customer <span class="text-danger">*</span></label>
        @if ($editing)
            {{-- Fixed after creation: moving a line between customers would
                 detach it from its own billing history. --}}
            <input type="text" class="form-control" disabled
                   value="{{ $subscription->customer->full_name }} ({{ $subscription->customer->account_number }})">
        @else
            <select name="customer_id" id="customer_id"
                    class="form-select @error('customer_id') is-invalid @enderror" required>
                <option value="">Select a customer</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((int) $currentCustomerId === $customer->id)>
                        {{ $customer->full_name }} — {{ $customer->account_number }}
                    </option>
                @endforeach
            </select>
            @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @endif
    </div>

    <div class="col-md-6">
        <label for="internet_plan_id" class="form-label">Internet plan <span class="text-danger">*</span></label>
        <select name="internet_plan_id" id="internet_plan_id"
                class="form-select @error('internet_plan_id') is-invalid @enderror" required>
            <option value="">Select a plan</option>
            @foreach ($plans as $plan)
                <option value="{{ $plan->id }}"
                        data-monthly="{{ $plan->monthly_price }}"
                        data-install="{{ $plan->installation_fee }}"
                        @selected((int) $currentPlanId === $plan->id)>
                    {{ $plan->name }} — &#8369;{{ number_format((float) $plan->monthly_price, 2) }}
                </option>
            @endforeach
        </select>
        @error('internet_plan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if ($editing)
            <div class="form-text">
                Changing the plan does not change the agreed rate below; adjust it yourself if it should move.
            </div>
        @endif
    </div>

    <div class="col-md-4">
        <label for="start_date" class="form-label">Start date <span class="text-danger">*</span></label>
        <input type="date" name="start_date" id="start_date"
               class="form-control @error('start_date') is-invalid @enderror"
               value="{{ old('start_date', $subscription?->start_date?->format('Y-m-d') ?? date('Y-m-d')) }}" required>
        @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="activation_date" class="form-label">Activation date</label>
        <input type="date" name="activation_date" id="activation_date"
               class="form-control @error('activation_date') is-invalid @enderror"
               value="{{ old('activation_date', $subscription?->activation_date?->format('Y-m-d')) }}">
        @error('activation_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Set automatically when the service is first activated.</div>
    </div>

    <div class="col-md-4">
        <label for="expiration_date" class="form-label">Expiration date</label>
        <input type="date" name="expiration_date" id="expiration_date"
               class="form-control @error('expiration_date') is-invalid @enderror"
               value="{{ old('expiration_date', $subscription?->expiration_date?->format('Y-m-d')) }}">
        @error('expiration_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Leave blank for an open-ended subscription.</div>
    </div>

    <div class="col-md-3">
        <label for="billing_day" class="form-label">Billing day <span class="text-danger">*</span></label>
        <input type="number" name="billing_day" id="billing_day" min="1" max="31"
               class="form-control @error('billing_day') is-invalid @enderror"
               value="{{ old('billing_day', $subscription->billing_day ?? 1) }}" required>
        @error('billing_day')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Day of month this line is billed.</div>
    </div>

    <div class="col-md-3">
        <label for="monthly_rate" class="form-label">Monthly rate <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">&#8369;</span>
            <input type="number" name="monthly_rate" id="monthly_rate" min="0" step="0.01"
                   class="form-control @error('monthly_rate') is-invalid @enderror"
                   value="{{ old('monthly_rate', $subscription->monthly_rate ?? '') }}" required>
            @error('monthly_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-text">Agreed rate. Kept even if the plan is repriced.</div>
    </div>

    <div class="col-md-3">
        <label for="installation_fee" class="form-label">Installation fee <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">&#8369;</span>
            <input type="number" name="installation_fee" id="installation_fee" min="0" step="0.01"
                   class="form-control @error('installation_fee') is-invalid @enderror"
                   value="{{ old('installation_fee', $subscription->installation_fee ?? '0.00') }}" required>
            @error('installation_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-3">
        <label for="discount_amount" class="form-label">Monthly discount <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">&#8369;</span>
            <input type="number" name="discount_amount" id="discount_amount" min="0" step="0.01"
                   class="form-control @error('discount_amount') is-invalid @enderror"
                   value="{{ old('discount_amount', $subscription->discount_amount ?? '0.00') }}" required>
            @error('discount_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-4">
        <label for="connection_type" class="form-label">Connection type <span class="text-danger">*</span></label>
        <select name="connection_type" id="connection_type"
                class="form-select @error('connection_type') is-invalid @enderror" required>
            @foreach ($connectionTypes as $type)
                <option value="{{ $type->value }}"
                    @selected(old('connection_type', $subscription->connection_type->value ?? 'fiber') === $type->value)>
                    {{ $type->label() }}
                </option>
            @endforeach
        </select>
        @error('connection_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="username" class="form-label">Service username</label>
        <input type="text" name="username" id="username"
               class="form-control @error('username') is-invalid @enderror"
               value="{{ old('username', $subscription->username ?? '') }}" placeholder="pppoe-username">
        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Reserved for the future PPPoE / RADIUS integration.</div>
    </div>

    <div class="col-md-4">
        <label for="static_ip" class="form-label">Static IP</label>
        <input type="text" name="static_ip" id="static_ip"
               class="form-control @error('static_ip') is-invalid @enderror"
               value="{{ old('static_ip', $subscription->static_ip ?? '') }}" placeholder="203.0.113.10">
        @error('static_ip')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="service_notes" class="form-label">Service notes</label>
        <textarea name="service_notes" id="service_notes" rows="3"
                  class="form-control @error('service_notes') is-invalid @enderror">{{ old('service_notes', $subscription->service_notes ?? '') }}</textarea>
        @error('service_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

@unless ($editing)
    @push('scripts')
    <script>
        // Prefill the agreed rate from the chosen plan. It stays editable,
        // because what the customer pays is what gets stored.
        document.getElementById('internet_plan_id')?.addEventListener('change', (event) => {
            const option = event.target.selectedOptions[0];
            if (!option || !option.dataset.monthly) {
                return;
            }
            document.getElementById('monthly_rate').value = option.dataset.monthly;
            document.getElementById('installation_fee').value = option.dataset.install;
        });
    </script>
    @endpush
@endunless
