@extends('layouts.app')

@section('title', 'Record payment')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('payments.index') }}">Payments</a></li>
    <li class="breadcrumb-item active" aria-current="page">Record payment</li>
@endsection

@section('content')
    {{-- Step one: choose the customer. Their outstanding invoices are what the
         allocation grid below is built from, so the page reloads on change. --}}
    <div class="card border-0 mb-3">
        <div class="card-header bg-white border-bottom fw-semibold text-navy">Customer</div>
        <div class="card-body">
            <form method="GET" action="{{ route('payments.create') }}">
                <label for="customer" class="form-label">Who is paying? <span class="text-danger">*</span></label>
                <div class="input-group">
                    <select name="customer" id="customer" class="form-select" onchange="this.form.submit()">
                        <option value="">Select a customer</option>
                        @foreach ($customers as $option)
                            <option value="{{ $option->id }}" @selected($customer?->id === $option->id)>
                                {{ $option->full_name }} — {{ $option->account_number }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-light border">Load invoices</button>
                </div>
            </form>
        </div>
    </div>

    @if (! $customer)
        <div class="empty-state card border-0">
            <i class="bi bi-person-lines-fill"></i>
            <p class="mb-0 mt-2">Choose a customer to see what they owe.</p>
        </div>
    @else
        <form method="POST" action="{{ route('payments.store') }}" novalidate>
            @csrf
            <input type="hidden" name="customer_id" value="{{ $customer->id }}">

            <div class="row g-3">
                <div class="col-12 col-lg-5">
                    <div class="card border-0">
                        <div class="card-header bg-white border-bottom fw-semibold text-navy">Payment details</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount received <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">&#8369;</span>
                                    <input type="number" step="0.01" min="0.01" name="amount" id="amount"
                                           class="form-control @error('amount') is-invalid @enderror"
                                           value="{{ old('amount') }}" required>
                                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-text">
                                    Anything not applied below is kept as credit on the account.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="payment_date" class="form-label">Payment date <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" id="payment_date"
                                       class="form-control @error('payment_date') is-invalid @enderror"
                                       value="{{ old('payment_date', now()->toDateString()) }}"
                                       max="{{ now()->toDateString() }}" required>
                                @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label for="payment_method" class="form-label">Method <span class="text-danger">*</span></label>
                                <select name="payment_method" id="payment_method"
                                        class="form-select @error('payment_method') is-invalid @enderror" required>
                                    @foreach ($methods as $method)
                                        <option value="{{ $method->value }}"
                                            @selected(old('payment_method', 'cash') === $method->value)>
                                            {{ $method->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label for="reference_number" class="form-label">External reference</label>
                                <input type="text" name="reference_number" id="reference_number"
                                       class="form-control @error('reference_number') is-invalid @enderror"
                                       value="{{ old('reference_number') }}"
                                       placeholder="Bank slip, GCash transaction id…">
                                @error('reference_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-0">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea name="notes" id="notes" rows="2"
                                          class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-7">
                    <div class="card border-0">
                        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                            <span class="fw-semibold text-navy">Apply to invoices</span>
                            @if ($outstanding->isNotEmpty())
                                <button type="button" class="btn btn-sm btn-light border" id="auto-apply">
                                    <i class="bi bi-magic me-1"></i> Apply oldest first
                                </button>
                            @endif
                        </div>

                        @error('allocations')
                            <div class="alert alert-danger m-3 mb-0 py-2 small">{{ $message }}</div>
                        @enderror

                        @if ($outstanding->isEmpty())
                            <div class="empty-state">
                                <i class="bi bi-check2-circle"></i>
                                <p class="mb-0 mt-2">
                                    {{ $customer->full_name }} has nothing outstanding.
                                    The payment will be held entirely as credit.
                                </p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-app table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Invoice</th>
                                            <th>Due</th>
                                            <th class="text-end">Balance</th>
                                            <th style="width:10rem;">Apply</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($outstanding as $invoice)
                                            <tr>
                                                <td>
                                                    <code class="small">{{ $invoice->invoice_number }}</code>
                                                    <span class="badge {{ $invoice->status->badgeClass() }}">
                                                        {{ $invoice->status->label() }}
                                                    </span>
                                                </td>
                                                <td class="small">
                                                    {{ $invoice->due_date->format('d M Y') }}
                                                    @if ($invoice->isOverdue())
                                                        <div class="text-danger">
                                                            {{ $invoice->daysOverdue() }} day(s) late
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="text-end small fw-medium invoice-balance"
                                                    data-balance="{{ $invoice->balance_due }}">
                                                    &#8369;{{ number_format((float) $invoice->balance_due, 2) }}
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0"
                                                           max="{{ $invoice->balance_due }}"
                                                           name="allocations[{{ $invoice->id }}]"
                                                           class="form-control form-control-sm allocation-input
                                                                  @error('allocations.'.$invoice->id) is-invalid @enderror"
                                                           value="{{ old('allocations.'.$invoice->id) }}"
                                                           placeholder="0.00">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="card-footer bg-white border-top">
                                <div class="d-flex justify-content-between small">
                                    <span class="text-secondary">Applied to invoices</span>
                                    <span id="applied-total" class="fw-medium">&#8369;0.00</span>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span class="text-secondary">Held as credit</span>
                                    <span id="credit-total">&#8369;0.00</span>
                                </div>
                                <div id="over-applied" class="text-danger small mt-1 d-none">
                                    More is being applied than was received.
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Record payment</button>
                <a href="{{ route('payments.index') }}" class="btn btn-light border">Cancel</a>
            </div>
        </form>

        @push('scripts')
        <script>
            (function () {
                const amountField = document.getElementById('amount');
                const inputs = Array.from(document.querySelectorAll('.allocation-input'));
                const money = (n) => '₱' + n.toFixed(2);

                function recalc() {
                    const amount = parseFloat(amountField.value) || 0;
                    const applied = inputs.reduce((sum, el) => sum + (parseFloat(el.value) || 0), 0);

                    document.getElementById('applied-total').textContent = money(applied);
                    document.getElementById('credit-total').textContent = money(Math.max(amount - applied, 0));
                    document.getElementById('over-applied')
                        .classList.toggle('d-none', applied <= amount + 0.001);
                }

                document.getElementById('auto-apply')?.addEventListener('click', () => {
                    let remaining = parseFloat(amountField.value) || 0;

                    inputs.forEach((input) => {
                        const balance = parseFloat(
                            input.closest('tr').querySelector('.invoice-balance').dataset.balance
                        ) || 0;
                        const apply = Math.min(remaining, balance);

                        input.value = apply > 0 ? apply.toFixed(2) : '';
                        remaining = Math.max(remaining - apply, 0);
                    });

                    recalc();
                });

                [amountField, ...inputs].forEach((el) => el.addEventListener('input', recalc));
                recalc();
            })();
        </script>
        @endpush
    @endif
@endsection
