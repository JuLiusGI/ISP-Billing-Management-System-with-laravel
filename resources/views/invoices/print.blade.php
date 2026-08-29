<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_number }} &middot; {{ $company['name'] }}</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body class="doc-body">

<div class="doc-toolbar d-print-none">
    <div class="container d-flex gap-2 align-items-center py-2">
        <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-light border">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <button type="button" class="btn btn-sm btn-primary ms-auto" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print
        </button>
    </div>
</div>

<main class="doc-sheet">
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
            <div class="h5 mb-1">INVOICE</div>
            <div class="small"><strong>{{ $invoice->invoice_number }}</strong></div>
            <div class="small text-secondary">Issued {{ $invoice->invoice_date->format('d M Y') }}</div>
            <div class="small text-secondary">Due {{ $invoice->due_date->format('d M Y') }}</div>
            <div class="mt-1">
                <span class="badge {{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span>
            </div>
        </div>
    </header>

    <section class="row g-4 mb-4">
        <div class="col-6">
            <div class="text-uppercase small text-secondary fw-semibold mb-1">Billed to</div>
            <div class="fw-medium">{{ $invoice->customer->full_name }}</div>
            <div class="small text-secondary">
                <div>Account {{ $invoice->customer->account_number }}</div>
                @if ($invoice->customer->primaryAddress)
                    <div>{{ $invoice->customer->primaryAddress->full_address }}</div>
                @endif
                <div>{{ $invoice->customer->contact_number }}</div>
                @if ($invoice->customer->email)<div>{{ $invoice->customer->email }}</div>@endif
            </div>
        </div>

        <div class="col-6">
            <div class="text-uppercase small text-secondary fw-semibold mb-1">Billing period</div>
            <div class="small">
                @if ($invoice->billing_period_start)
                    {{ $invoice->billing_period_start->format('d M Y') }} –
                    {{ $invoice->billing_period_end?->format('d M Y') }}
                @else
                    Not tied to a billing period
                @endif
            </div>
        </div>
    </section>

    <table class="table table-sm doc-table mb-4">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-end" style="width:5rem;">Qty</th>
                <th class="text-end" style="width:8rem;">Unit price</th>
                <th class="text-end" style="width:8rem;">Discount</th>
                <th class="text-end" style="width:8rem;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>
                        {{ $item->description }}
                        <div class="small text-secondary">{{ $item->item_type->label() }}</div>
                    </td>
                    <td class="text-end">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                    <td class="text-end">&#8369;{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="text-end">&#8369;{{ number_format((float) $item->discount_amount, 2) }}</td>
                    <td class="text-end">&#8369;{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="row">
        <div class="col-7">
            @if ($invoice->notes)
                <div class="text-uppercase small text-secondary fw-semibold mb-1">Notes</div>
                <div class="small" style="white-space: pre-line;">{{ $invoice->notes }}</div>
            @endif
        </div>

        <div class="col-5">
            <table class="table table-sm doc-totals mb-0">
                <tr>
                    <td class="text-secondary">Subtotal</td>
                    <td class="text-end">&#8369;{{ number_format((float) $invoice->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td class="text-secondary">Discount</td>
                    <td class="text-end">−&#8369;{{ number_format((float) $invoice->discount_total, 2) }}</td>
                </tr>
                @if ((float) $invoice->charges_total > 0)
                    <tr>
                        <td class="text-secondary">Additional charges</td>
                        <td class="text-end">&#8369;{{ number_format((float) $invoice->charges_total, 2) }}</td>
                    </tr>
                @endif
                @if ((float) $invoice->tax_total > 0)
                    <tr>
                        <td class="text-secondary">Tax</td>
                        <td class="text-end">&#8369;{{ number_format((float) $invoice->tax_total, 2) }}</td>
                    </tr>
                @endif
                <tr class="fw-semibold border-top">
                    <td>Total</td>
                    <td class="text-end">&#8369;{{ number_format((float) $invoice->total_amount, 2) }}</td>
                </tr>
                <tr>
                    <td class="text-secondary">Amount paid</td>
                    <td class="text-end">&#8369;{{ number_format((float) $invoice->amount_paid, 2) }}</td>
                </tr>
                <tr class="fw-bold">
                    <td>Balance due</td>
                    <td class="text-end">&#8369;{{ number_format((float) $invoice->balance_due, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <footer class="border-top mt-5 pt-3 small text-secondary text-center">
        Thank you for your business.
        @if ($invoice->status !== App\Enums\InvoiceStatus::Paid)
            Please settle by {{ $invoice->due_date->format('d M Y') }}.
        @endif
    </footer>
</main>

</body>
</html>
