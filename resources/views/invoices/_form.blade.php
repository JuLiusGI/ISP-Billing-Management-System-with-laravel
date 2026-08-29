@php
    /** @var \App\Models\Invoice|null $invoice */
    $invoice = $invoice ?? null;
    $selectedCustomer = $selectedCustomer ?? null;
    $editing = $invoice !== null;

    $rows = old('items', $editing
        ? $invoice->items->map(fn ($i) => [
            'description' => $i->description,
            'item_type' => $i->item_type->value,
            'quantity' => $i->quantity,
            'unit_price' => $i->unit_price,
            'discount_amount' => $i->discount_amount,
        ])->all()
        : [['description' => '', 'item_type' => 'subscription', 'quantity' => '1.00', 'unit_price' => '', 'discount_amount' => '0.00']]);
@endphp

<div class="row g-3 mb-4">
    <div class="col-md-5">
        <label for="customer_id" class="form-label">Customer <span class="text-danger">*</span></label>
        @if ($editing)
            {{-- Fixed after issue: moving an invoice between customers would
                 falsify both customers' billing histories. --}}
            <input type="text" class="form-control" disabled
                   value="{{ $invoice->customer->full_name }} ({{ $invoice->customer->account_number }})">
            <input type="hidden" name="customer_id" value="{{ $invoice->customer_id }}">
        @else
            <select name="customer_id" id="customer_id"
                    class="form-select @error('customer_id') is-invalid @enderror" required>
                <option value="">Select a customer</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}"
                        @selected((int) old('customer_id', $selectedCustomer?->id) === $customer->id)>
                        {{ $customer->full_name }} — {{ $customer->account_number }}
                    </option>
                @endforeach
            </select>
            @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @endif
    </div>

    <div class="col-md-3">
        <label for="invoice_date" class="form-label">Invoice date <span class="text-danger">*</span></label>
        <input type="date" name="invoice_date" id="invoice_date"
               class="form-control @error('invoice_date') is-invalid @enderror"
               value="{{ old('invoice_date', $invoice?->invoice_date?->format('Y-m-d') ?? ($defaultInvoiceDate ?? '')) }}"
               required>
        @error('invoice_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="due_date" class="form-label">Due date <span class="text-danger">*</span></label>
        <input type="date" name="due_date" id="due_date"
               class="form-control @error('due_date') is-invalid @enderror"
               value="{{ old('due_date', $invoice?->due_date?->format('Y-m-d') ?? ($defaultDueDate ?? '')) }}"
               required>
        @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Defaults to the configured grace period.</div>
    </div>

    <div class="col-md-3">
        <label for="billing_period_start" class="form-label">Period start</label>
        <input type="date" name="billing_period_start" id="billing_period_start"
               class="form-control @error('billing_period_start') is-invalid @enderror"
               value="{{ old('billing_period_start', $invoice?->billing_period_start?->format('Y-m-d')) }}">
        @error('billing_period_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="billing_period_end" class="form-label">Period end</label>
        <input type="date" name="billing_period_end" id="billing_period_end"
               class="form-control @error('billing_period_end') is-invalid @enderror"
               value="{{ old('billing_period_end', $invoice?->billing_period_end?->format('Y-m-d')) }}">
        @error('billing_period_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<h3 class="h6 text-navy border-bottom pb-2 mb-3">Line items</h3>

@error('items')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

<div class="table-responsive">
    <table class="table table-sm align-middle" id="item-table">
        <thead class="table-light">
            <tr>
                <th style="min-width:14rem;">Description</th>
                <th style="min-width:9rem;">Type</th>
                <th style="width:7rem;">Qty</th>
                <th style="width:9rem;">Unit price</th>
                <th style="width:9rem;">Discount</th>
                <th style="width:9rem;" class="text-end">Line total</th>
                <th style="width:3rem;"></th>
            </tr>
        </thead>
        <tbody id="item-rows">
            @foreach ($rows as $index => $row)
                <tr class="item-row">
                    <td>
                        <input type="text" name="items[{{ $index }}][description]"
                               class="form-control form-control-sm @error("items.$index.description") is-invalid @enderror"
                               value="{{ $row['description'] ?? '' }}" required>
                        @error("items.$index.description")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </td>
                    <td>
                        <select name="items[{{ $index }}][item_type]" class="form-select form-select-sm">
                            @foreach ($itemTypes as $type)
                                <option value="{{ $type->value }}"
                                    @selected(($row['item_type'] ?? 'subscription') === $type->value)>
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0.01" name="items[{{ $index }}][quantity]"
                               class="form-control form-control-sm item-qty @error("items.$index.quantity") is-invalid @enderror"
                               value="{{ $row['quantity'] ?? '1.00' }}" required>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" name="items[{{ $index }}][unit_price]"
                               class="form-control form-control-sm item-price @error("items.$index.unit_price") is-invalid @enderror"
                               value="{{ $row['unit_price'] ?? '' }}" required>
                        @error("items.$index.unit_price")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" name="items[{{ $index }}][discount_amount]"
                               class="form-control form-control-sm item-discount @error("items.$index.discount_amount") is-invalid @enderror"
                               value="{{ $row['discount_amount'] ?? '0.00' }}" required>
                        @error("items.$index.discount_amount")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </td>
                    <td class="text-end"><span class="item-total small fw-medium">0.00</span></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-light border remove-row" aria-label="Remove line">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<button type="button" class="btn btn-sm btn-light border" id="add-row">
    <i class="bi bi-plus-lg me-1"></i> Add line
</button>

<div class="row g-3 mt-3">
    <div class="col-md-6">
        <label for="notes" class="form-label">Notes</label>
        <textarea name="notes" id="notes" rows="4"
                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $invoice->notes ?? '') }}</textarea>
        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <div class="row g-2">
            <div class="col-12">
                <label for="discount_total" class="form-label">Invoice discount <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">&#8369;</span>
                    <input type="number" step="0.01" min="0" name="discount_total" id="discount_total"
                           class="form-control @error('discount_total') is-invalid @enderror"
                           value="{{ old('discount_total', $editing ? $invoiceLevelDiscount ?? '0.00' : '0.00') }}" required>
                    @error('discount_total')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-text">Applied on top of any per-line discounts.</div>
            </div>

            <div class="col-12">
                <label for="charges_total" class="form-label">Additional charges <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">&#8369;</span>
                    <input type="number" step="0.01" min="0" name="charges_total" id="charges_total"
                           class="form-control @error('charges_total') is-invalid @enderror"
                           value="{{ old('charges_total', $invoice->charges_total ?? '0.00') }}" required>
                    @error('charges_total')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="col-12">
                <div class="border rounded p-3 bg-light">
                    <div class="d-flex justify-content-between small">
                        <span class="text-secondary">Line items</span>
                        <span id="preview-lines">&#8369;0.00</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-secondary">Invoice discount</span>
                        <span id="preview-discount">−&#8369;0.00</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-secondary">Additional charges</span>
                        <span id="preview-charges">&#8369;0.00</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between fw-semibold">
                        <span>Estimated total</span>
                        <span id="preview-total" class="text-navy">&#8369;0.00</span>
                    </div>
                    <div class="form-text mb-0">
                        A preview only. The server recalculates every figure on save, tax included.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const rows = document.getElementById('item-rows');
        const money = (n) => '₱' + n.toFixed(2);

        function recalc() {
            let lines = 0;

            rows.querySelectorAll('.item-row').forEach((row) => {
                const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
                const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
                const discount = parseFloat(row.querySelector('.item-discount')?.value) || 0;
                const total = Math.max(qty * price - discount, 0);

                row.querySelector('.item-total').textContent = total.toFixed(2);
                lines += total;
            });

            const invoiceDiscount = parseFloat(document.getElementById('discount_total').value) || 0;
            const charges = parseFloat(document.getElementById('charges_total').value) || 0;

            document.getElementById('preview-lines').textContent = money(lines);
            document.getElementById('preview-discount').textContent = '−' + money(invoiceDiscount);
            document.getElementById('preview-charges').textContent = money(charges);
            document.getElementById('preview-total').textContent =
                money(Math.max(lines - invoiceDiscount + charges, 0));
        }

        function reindex() {
            rows.querySelectorAll('.item-row').forEach((row, index) => {
                row.querySelectorAll('input, select').forEach((field) => {
                    field.name = field.name.replace(/items\[\d+\]/, 'items[' + index + ']');
                });
            });
        }

        document.getElementById('add-row').addEventListener('click', () => {
            const clone = rows.querySelector('.item-row').cloneNode(true);

            clone.querySelectorAll('input').forEach((field) => {
                field.classList.remove('is-invalid');
                if (field.classList.contains('item-qty')) {
                    field.value = '1.00';
                } else if (field.classList.contains('item-discount')) {
                    field.value = '0.00';
                } else {
                    field.value = '';
                }
            });
            clone.querySelectorAll('.invalid-feedback').forEach((el) => el.remove());

            rows.appendChild(clone);
            reindex();
            recalc();
        });

        rows.addEventListener('click', (event) => {
            if (!event.target.closest('.remove-row')) {
                return;
            }
            // Always leave one line: an invoice needs at least one.
            if (rows.querySelectorAll('.item-row').length === 1) {
                return;
            }
            event.target.closest('.item-row').remove();
            reindex();
            recalc();
        });

        document.addEventListener('input', (event) => {
            if (event.target.closest('#item-table') ||
                ['discount_total', 'charges_total'].includes(event.target.id)) {
                recalc();
            }
        });

        recalc();
    })();
</script>
@endpush
