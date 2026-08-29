@extends('layouts.app')

@section('title', $invoice->invoice_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('invoices.index') }}">Invoices</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $invoice->invoice_number }}</li>
@endsection

@section('content')
    @if ($invoice->status === App\Enums\InvoiceStatus::Cancelled)
        <div class="alert alert-dark d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-x-circle mt-1"></i>
            <div class="small">
                <strong>Cancelled</strong>
                on {{ $invoice->cancelled_at?->format('d M Y') }}
                by {{ $invoice->cancelledBy?->full_name ?? 'system' }}.
                @if ($invoice->cancellation_reason)
                    Reason: {{ $invoice->cancellation_reason }}
                @endif
            </div>
        </div>
    @endif

    <div class="card border-0 mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center">
            <div class="flex-grow-1">
                <h2 class="h5 mb-1 text-navy">
                    {{ $invoice->invoice_number }}
                    <span class="badge {{ $invoice->status->badgeClass() }} align-middle">
                        {{ $invoice->status->label() }}
                    </span>
                </h2>
                <div class="small text-secondary">
                    <a href="{{ route('customers.show', $invoice->customer) }}" class="text-decoration-none">
                        {{ $invoice->customer->full_name }} ({{ $invoice->customer->account_number }})
                    </a>
                    &middot; issued {{ $invoice->invoice_date->format('d M Y') }}
                    &middot; due {{ $invoice->due_date->format('d M Y') }}
                    @if ($invoice->isOverdue())
                        <span class="text-danger">— {{ $invoice->daysOverdue() }} day(s) overdue</span>
                    @endif
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="btn btn-sm btn-light border">
                    <i class="bi bi-printer me-1"></i> Print
                </a>
                @can('update', $invoice)
                    <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </a>
                @endcan
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card border-0 mb-3">
                <div class="card-header bg-white border-bottom fw-semibold text-navy">Line items</div>
                <div class="table-responsive">
                    <table class="table table-app mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Description</th>
                                <th>Type</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Unit price</th>
                                <th class="text-end">Discount</th>
                                <th class="text-end">Line total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td class="small">{{ $item->description }}</td>
                                    <td class="small text-secondary">{{ $item->item_type->label() }}</td>
                                    <td class="text-end small">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                                    <td class="text-end small">&#8369;{{ number_format((float) $item->unit_price, 2) }}</td>
                                    <td class="text-end small">&#8369;{{ number_format((float) $item->discount_amount, 2) }}</td>
                                    <td class="text-end small fw-medium">&#8369;{{ number_format((float) $item->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0">
                <div class="card-header bg-white border-bottom fw-semibold text-navy">Payments applied</div>
                <div class="card-body">
                    @forelse ($invoice->allocations as $allocation)
                        <div class="d-flex justify-content-between align-items-center {{ $loop->last ? '' : 'border-bottom' }} py-2 small">
                            <div>
                                <code>{{ $allocation->payment->payment_reference }}</code>
                                <span class="badge {{ $allocation->payment->status->badgeClass() }} ms-1">
                                    {{ $allocation->payment->status->label() }}
                                </span>
                                <div class="text-secondary">
                                    {{ $allocation->payment->payment_date->format('d M Y') }}
                                    &middot; {{ $allocation->payment->payment_method->label() }}
                                </div>
                            </div>
                            <div class="fw-medium">&#8369;{{ number_format((float) $allocation->amount, 2) }}</div>
                        </div>
                    @empty
                        <p class="small text-secondary mb-0">No payments applied to this invoice yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-0 mb-3">
                <div class="card-header bg-white border-bottom fw-semibold text-navy">Summary</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-7 text-secondary fw-normal">Subtotal</dt>
                        <dd class="col-5 text-end">&#8369;{{ number_format((float) $invoice->subtotal, 2) }}</dd>

                        <dt class="col-7 text-secondary fw-normal">Discount</dt>
                        <dd class="col-5 text-end">−&#8369;{{ number_format((float) $invoice->discount_total, 2) }}</dd>

                        <dt class="col-7 text-secondary fw-normal">Additional charges</dt>
                        <dd class="col-5 text-end">&#8369;{{ number_format((float) $invoice->charges_total, 2) }}</dd>

                        @if ((float) $invoice->tax_total > 0)
                            <dt class="col-7 text-secondary fw-normal">Tax</dt>
                            <dd class="col-5 text-end">&#8369;{{ number_format((float) $invoice->tax_total, 2) }}</dd>
                        @endif

                        <dt class="col-7 fw-semibold border-top pt-2">Total</dt>
                        <dd class="col-5 text-end fw-semibold border-top pt-2">
                            &#8369;{{ number_format((float) $invoice->total_amount, 2) }}
                        </dd>

                        <dt class="col-7 text-secondary fw-normal">Paid</dt>
                        <dd class="col-5 text-end">&#8369;{{ number_format((float) $invoice->amount_paid, 2) }}</dd>

                        <dt class="col-7 fw-semibold">Balance due</dt>
                        <dd class="col-5 text-end fw-semibold text-danger mb-0">
                            &#8369;{{ number_format((float) $invoice->balance_due, 2) }}
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card border-0 mb-3">
                <div class="card-header bg-white border-bottom fw-semibold text-navy">Details</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-secondary fw-normal">Billing period</dt>
                        <dd class="col-7">
                            @if ($invoice->billing_period_start)
                                {{ $invoice->billing_period_start->format('d M') }} –
                                {{ $invoice->billing_period_end?->format('d M Y') }}
                            @else
                                —
                            @endif
                        </dd>

                        <dt class="col-5 text-secondary fw-normal">Subscription</dt>
                        <dd class="col-7">
                            @if ($invoice->subscription)
                                <a href="{{ route('subscriptions.show', $invoice->subscription) }}"
                                   class="text-decoration-none">{{ $invoice->subscription->subscription_code }}</a>
                            @else
                                Ad-hoc invoice
                            @endif
                        </dd>

                        <dt class="col-5 text-secondary fw-normal">Cycle</dt>
                        <dd class="col-7">{{ $invoice->billingCycle?->name ?? '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Created by</dt>
                        <dd class="col-7 mb-0">{{ $invoice->createdBy?->full_name ?? 'System' }}</dd>
                    </dl>

                    @if ($invoice->notes)
                        <hr>
                        <div class="small" style="white-space: pre-line;">{{ $invoice->notes }}</div>
                    @endif
                </div>
            </div>

            @can('cancel', $invoice)
                <div class="card border-0">
                    <div class="card-header bg-white border-bottom fw-semibold text-navy">Cancel invoice</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('invoices.cancel', $invoice) }}"
                              data-confirm="Cancel {{ $invoice->invoice_number }}? The invoice is kept with a zero balance.">
                            @csrf
                            @method('PATCH')

                            <label for="reason" class="form-label small">Reason <span class="text-danger">*</span></label>
                            <input type="text" name="reason" id="reason" maxlength="255" required
                                   class="form-control form-control-sm @error('reason') is-invalid @enderror"
                                   value="{{ old('reason') }}">
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror

                            <button type="submit" class="btn btn-outline-danger btn-sm w-100 mt-2">
                                <i class="bi bi-x-circle me-1"></i> Cancel this invoice
                            </button>
                        </form>
                        <p class="form-text mb-0 mt-2">
                            Cancelled invoices are kept, never deleted.
                        </p>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
