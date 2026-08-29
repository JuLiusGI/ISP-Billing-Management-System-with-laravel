{{-- The receipt itself. Shared by the on-screen view and the print view so
     the two can never show different figures. --}}
@php
    $customer = $payment->customer;
    $reversed = $payment->isReversed();
@endphp

@if ($reversed)
    <div class="alert alert-danger py-2 small mb-4" role="alert">
        <strong>VOID —</strong> the payment this receipt acknowledges was reversed on
        {{ $payment->reversed_at?->format('d M Y') }}. This receipt is no longer valid.
    </div>
@endif

<header class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
    <div>
        <h1 class="h4 text-navy mb-1">{{ $company['name'] }}</h1>
        <div class="small text-secondary">
            @if ($company['address'])<div>{{ $company['address'] }}</div>@endif
            @if ($company['phone'])<div>Tel: {{ $company['phone'] }}</div>@endif
            @if ($company['email'])<div>{{ $company['email'] }}</div>@endif
            @if ($company['website'])<div>{{ $company['website'] }}</div>@endif
        </div>
    </div>

    <div class="text-end">
        <div class="h5 mb-1">OFFICIAL RECEIPT</div>
        <div class="small"><strong>{{ $receipt->receipt_number }}</strong></div>
        <div class="small text-secondary">Issued {{ $receipt->issued_at->format('d M Y, g:i A') }}</div>
        @if ($reversed)
            <div class="mt-1"><span class="badge text-bg-danger">VOID</span></div>
        @endif
    </div>
</header>

<section class="row g-4 mb-4">
    <div class="col-6">
        <div class="text-uppercase small text-secondary fw-semibold mb-1">Received from</div>
        <div class="fw-medium">{{ $customer->full_name }}</div>
        <div class="small text-secondary">
            <div>Account {{ $customer->account_number }}</div>
            @if ($customer->primaryAddress)
                <div>{{ $customer->primaryAddress->full_address }}</div>
            @endif
            <div>{{ $customer->contact_number }}</div>
        </div>
    </div>

    <div class="col-6">
        <div class="text-uppercase small text-secondary fw-semibold mb-1">Payment</div>
        <dl class="row mb-0 small">
            <dt class="col-6 text-secondary fw-normal">Reference</dt>
            <dd class="col-6">{{ $payment->payment_reference }}</dd>

            <dt class="col-6 text-secondary fw-normal">Date</dt>
            <dd class="col-6">{{ $payment->payment_date->format('d M Y') }}</dd>

            <dt class="col-6 text-secondary fw-normal">Method</dt>
            <dd class="col-6">{{ $payment->payment_method->label() }}</dd>

            @if ($payment->reference_number)
                <dt class="col-6 text-secondary fw-normal">External ref.</dt>
                <dd class="col-6 mb-0">{{ $payment->reference_number }}</dd>
            @endif
        </dl>
    </div>
</section>

<div class="text-uppercase small text-secondary fw-semibold mb-1">Applied to</div>

@if ($payment->allocations->isEmpty())
    <p class="small text-secondary">
        Not applied to any invoice. The full amount is held as credit on the account.
    </p>
@else
    <table class="table table-sm doc-table mb-4">
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Billing period</th>
                <th class="text-end" style="width:9rem;">Applied</th>
                <th class="text-end" style="width:10rem;">Remaining balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payment->allocations as $allocation)
                <tr>
                    <td>{{ $allocation->invoice->invoice_number }}</td>
                    <td class="small text-secondary">
                        @if ($allocation->invoice->billing_period_start)
                            {{ $allocation->invoice->billing_period_start->format('d M Y') }} –
                            {{ $allocation->invoice->billing_period_end?->format('d M Y') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-end">&#8369;{{ number_format((float) $allocation->amount, 2) }}</td>
                    <td class="text-end">
                        &#8369;{{ number_format((float) $allocation->invoice->balance_due, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="row">
    <div class="col-7">
        @if ($payment->notes)
            <div class="text-uppercase small text-secondary fw-semibold mb-1">Notes</div>
            <div class="small" style="white-space: pre-line;">{{ $payment->notes }}</div>
        @endif
    </div>

    <div class="col-5">
        <table class="table table-sm doc-totals mb-0">
            <tr>
                <td class="text-secondary">Applied to invoices</td>
                <td class="text-end">&#8369;{{ number_format((float) $payment->allocated_amount, 2) }}</td>
            </tr>
            <tr>
                <td class="text-secondary">Held as credit</td>
                <td class="text-end">&#8369;{{ number_format((float) $payment->unallocatedAmount(), 2) }}</td>
            </tr>
            <tr class="fw-bold border-top">
                <td>Amount paid</td>
                <td class="text-end">&#8369;{{ number_format((float) $payment->amount, 2) }}</td>
            </tr>
        </table>
    </div>
</div>

<footer class="border-top mt-5 pt-3 small">
    <div class="row">
        <div class="col-6 text-secondary">
            <div>Received by</div>
            <div class="fw-medium text-dark mt-3 pt-2 border-top d-inline-block" style="min-width:14rem;">
                {{ $payment->receivedBy?->full_name ?? '—' }}
            </div>
        </div>
        <div class="col-6 text-end text-secondary">
            <div>Issued by</div>
            <div class="fw-medium text-dark mt-3 pt-2 border-top d-inline-block" style="min-width:14rem;">
                {{ $receipt->issuedBy?->full_name ?? '—' }}
            </div>
        </div>
    </div>

    <p class="text-center text-secondary mt-4 mb-0">
        This receipt acknowledges the payment above. Keep it for your records.
    </p>
</footer>
