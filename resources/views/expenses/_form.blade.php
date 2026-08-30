@php
    /** @var \App\Models\Expense|null $expense */
    // Bound explicitly: the create form has no $expense, and an undefined
    // variable is an exception here rather than a warning.
    $expense = $expense ?? null;
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label for="expense_date" class="form-label">Date <span class="text-danger">*</span></label>
        <input type="date" name="expense_date" id="expense_date"
               class="form-control @error('expense_date') is-invalid @enderror"
               value="{{ old('expense_date', $expense?->expense_date?->format('Y-m-d') ?? now()->toDateString()) }}"
               max="{{ now()->toDateString() }}" required>
        @error('expense_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="expense_category_id" class="form-label">Category <span class="text-danger">*</span></label>
        <select name="expense_category_id" id="expense_category_id"
                class="form-select @error('expense_category_id') is-invalid @enderror" required>
            <option value="">Choose a category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}"
                    @selected(old('expense_category_id', $expense?->expense_category_id) == $category->id)>
                    {{ $category->name }}@unless ($category->is_active) (retired)@endunless
                </option>
            @endforeach
        </select>
        @error('expense_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">&#8369;</span>
            <input type="number" name="amount" id="amount" step="0.01" min="0.01"
                   class="form-control @error('amount') is-invalid @enderror"
                   value="{{ old('amount', $expense?->amount) }}" required>
            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
        <input type="text" name="description" id="description"
               class="form-control @error('description') is-invalid @enderror"
               value="{{ old('description', $expense?->description) }}" required>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="payment_method" class="form-label">Paid by <span class="text-danger">*</span></label>
        <select name="payment_method" id="payment_method"
                class="form-select @error('payment_method') is-invalid @enderror" required>
            @foreach ($methods as $method)
                <option value="{{ $method->value }}"
                    @selected(old('payment_method', $expense?->payment_method?->value ?? 'cash') === $method->value)>
                    {{ $method->label() }}
                </option>
            @endforeach
        </select>
        @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="vendor" class="form-label">Vendor / payee</label>
        <input type="text" name="vendor" id="vendor"
               class="form-control @error('vendor') is-invalid @enderror"
               value="{{ old('vendor', $expense?->vendor) }}">
        @error('vendor')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notes</label>
        <textarea name="notes" id="notes" rows="3"
                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $expense?->notes) }}</textarea>
        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
