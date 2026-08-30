@extends('layouts.app')

@section('title', $customer->full_name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('customers.index') }}">Customers</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $customer->account_number }}</li>
@endsection

@section('content')
    @if ($customer->trashed())
        <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-archive"></i>
            <div>This customer is archived. Their records are retained but they are hidden from the active list.</div>
        </div>
    @endif

    {{-- Header ------------------------------------------------------------ --}}
    <div class="card border-0 mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            @if ($customer->photo_path)
                <img src="{{ route('customers.photo', $customer) }}" alt="{{ $customer->full_name }}"
                     class="rounded-circle" style="width:4rem;height:4rem;object-fit:cover;">
            @else
                <span class="app-avatar" style="width:4rem;height:4rem;font-size:1.25rem;">
                    {{ strtoupper(substr($customer->first_name, 0, 1) . substr($customer->last_name, 0, 1)) }}
                </span>
            @endif

            <div class="flex-grow-1">
                <h2 class="h5 mb-1 text-navy">{{ $customer->full_name }}</h2>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <code class="small">{{ $customer->account_number }}</code>
                    <span class="badge {{ $customer->status->badgeClass() }}">{{ $customer->status->label() }}</span>
                    <span class="badge {{ $customer->account_status->badgeClass() }}">{{ $customer->account_status->label() }}</span>
                    <span class="badge {{ $customer->connection_status->badgeClass() }}">{{ $customer->connection_status->label() }}</span>
                    <span class="badge text-bg-light border">{{ $customer->customer_type->label() }}</span>
                </div>
            </div>

            <div class="d-flex gap-2">
                @can('update', $customer)
                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </a>
                @endcan
                @can('restore', $customer)
                    <form method="POST" action="{{ route('customers.restore', $customer->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-light border">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    {{-- Billing summary ---------------------------------------------------- --}}
    <div class="row g-3 mb-3">
        @foreach ([
            ['label' => 'Outstanding balance', 'value' => $outstandingBalance, 'accent' => 'danger'],
            ['label' => 'Total invoiced', 'value' => $totalInvoiced, 'accent' => 'primary'],
            ['label' => 'Total paid', 'value' => $totalPaid, 'accent' => 'success'],
            ['label' => 'Unapplied credit', 'value' => $availableCredit, 'accent' => 'warning'],
        ] as $stat)
            <div class="col-6 col-lg-3">
                <div class="card border-0 h-100">
                    <div class="card-body">
                        <div class="text-secondary small">{{ $stat['label'] }}</div>
                        <div class="fs-5 fw-bold text-{{ $stat['accent'] }}">
                            &#8369;{{ number_format((float) $stat['value'], 2) }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Tabs --------------------------------------------------------------- --}}
    <div class="card border-0">
        <div class="card-header bg-white border-bottom pb-0">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                @foreach ([
                    'details' => 'Details',
                    'service' => 'Internet service',
                    'invoices' => 'Invoices',
                    'payments' => 'Payments',
                    'history' => 'Service history',
                ] as $key => $label)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link @if ($loop->first) active @endif" data-bs-toggle="tab"
                                data-bs-target="#tab-{{ $key }}" type="button" role="tab">
                            {{ $label }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body tab-content">
            {{-- Details --}}
            <div class="tab-pane fade show active" id="tab-details" role="tabpanel">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h3 class="h6 text-navy">Contact</h3>
                        <dl class="row mb-0 small">
                            <dt class="col-5 text-secondary fw-normal">Contact number</dt>
                            <dd class="col-7">{{ $customer->contact_number }}</dd>

                            <dt class="col-5 text-secondary fw-normal">Alternative</dt>
                            <dd class="col-7">{{ $customer->alternate_contact_number ?: '—' }}</dd>

                            <dt class="col-5 text-secondary fw-normal">Email</dt>
                            <dd class="col-7">{{ $customer->email ?: '—' }}</dd>

                            <dt class="col-5 text-secondary fw-normal">Date of birth</dt>
                            <dd class="col-7">{{ $customer->date_of_birth?->format('d M Y') ?? '—' }}</dd>

                            <dt class="col-5 text-secondary fw-normal">Gender</dt>
                            <dd class="col-7">{{ $customer->gender ? ucfirst($customer->gender) : '—' }}</dd>
                        </dl>
                    </div>

                    <div class="col-md-6">
                        <h3 class="h6 text-navy">Service address</h3>
                        <p class="small mb-3">{{ $customer->primaryAddress?->full_address ?: '—' }}</p>

                        <h3 class="h6 text-navy">Account</h3>
                        <dl class="row mb-0 small">
                            <dt class="col-5 text-secondary fw-normal">Installed</dt>
                            <dd class="col-7">{{ $customer->installation_date?->format('d M Y') ?? '—' }}</dd>

                            <dt class="col-5 text-secondary fw-normal">Added by</dt>
                            <dd class="col-7">{{ $customer->createdBy?->full_name ?? '—' }}</dd>

                            <dt class="col-5 text-secondary fw-normal">Added on</dt>
                            <dd class="col-7">{{ $customer->created_at->format('d M Y') }}</dd>
                        </dl>
                    </div>

                    <div class="col-md-6">
                        <h3 class="h6 text-navy">Additional contacts</h3>
                        @forelse ($customer->contacts as $contact)
                            <div class="small mb-2">
                                <span class="fw-medium">{{ $contact->name }}</span>
                                @if ($contact->relationship)
                                    <span class="text-secondary">({{ $contact->relationship }})</span>
                                @endif
                                <div class="text-secondary">
                                    {{ $contact->contact_number }}{{ $contact->email ? ' · '.$contact->email : '' }}
                                </div>
                            </div>
                        @empty
                            <p class="small text-secondary mb-0">None recorded.</p>
                        @endforelse
                    </div>

                    <div class="col-md-6">
                        <h3 class="h6 text-navy">Notes</h3>
                        <p class="small text-secondary mb-0" style="white-space: pre-line;">{{ $customer->notes ?: 'No notes.' }}</p>
                    </div>
                </div>
            </div>

            {{-- Internet service --}}
            <div class="tab-pane fade" id="tab-service" role="tabpanel">
                @can('subscriptions.create')
                    <div class="d-flex justify-content-end mb-2">
                        <a href="{{ route('subscriptions.create', ['customer' => $customer->id]) }}"
                           class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Add subscription
                        </a>
                    </div>
                @endcan

                @forelse ($customer->subscriptions as $subscription)
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <a href="{{ route('subscriptions.show', $subscription) }}"
                                   class="fw-medium text-decoration-none">
                                    {{ $subscription->internetPlan->name }}
                                </a>
                                <div class="small text-secondary">
                                    {{ $subscription->internetPlan->speed_label }}
                                    &middot; <code>{{ $subscription->subscription_code }}</code>
                                </div>
                            </div>
                            <span class="badge {{ $subscription->status->badgeClass() }}">
                                {{ $subscription->status->label() }}
                            </span>
                        </div>
                        <dl class="row mb-0 small mt-2">
                            <dt class="col-5 col-md-3 text-secondary fw-normal">Billed monthly</dt>
                            <dd class="col-7 col-md-3">&#8369;{{ number_format((float) $subscription->net_monthly_rate, 2) }}</dd>
                            <dt class="col-5 col-md-3 text-secondary fw-normal">Billing day</dt>
                            <dd class="col-7 col-md-3">{{ $subscription->billing_day }}</dd>
                        </dl>
                    </div>
                @empty
                    <div class="empty-state">
                        <i class="bi bi-wifi-off"></i>
                        <p class="mb-0 mt-2">No internet subscription yet.</p>
                    </div>
                @endforelse
            </div>

            {{-- Invoices --}}
            <div class="tab-pane fade" id="tab-invoices" role="tabpanel">
                @can('invoices.create')
                    <div class="d-flex justify-content-end mb-2">
                        <a href="{{ route('invoices.create', ['customer' => $customer->id]) }}"
                           class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Create invoice
                        </a>
                    </div>
                @endcan

                @if ($invoices->isEmpty())
                    <div class="empty-state">
                        <i class="bi bi-receipt"></i>
                        <p class="mb-0 mt-2">No invoices yet.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-app table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice</th><th>Date</th><th>Due</th>
                                    <th class="text-end">Total</th><th class="text-end">Balance</th><th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoices as $invoice)
                                    <tr>
                                        <td>
                                            <a href="{{ route('invoices.show', $invoice) }}" class="text-decoration-none">
                                                <code class="small">{{ $invoice->invoice_number }}</code>
                                            </a>
                                        </td>
                                        <td class="small">{{ $invoice->invoice_date->format('d M Y') }}</td>
                                        <td class="small">{{ $invoice->due_date->format('d M Y') }}</td>
                                        <td class="text-end small">&#8369;{{ number_format((float) $invoice->total_amount, 2) }}</td>
                                        <td class="text-end small">&#8369;{{ number_format((float) $invoice->balance_due, 2) }}</td>
                                        <td>
                                            <span class="badge {{ $invoice->status->badgeClass() }}">
                                                {{ $invoice->status->label() }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Payments --}}
            <div class="tab-pane fade" id="tab-payments" role="tabpanel">
                @can('payments.create')
                    <div class="d-flex justify-content-end mb-2">
                        <a href="{{ route('payments.create', ['customer' => $customer->id]) }}"
                           class="btn btn-sm btn-primary">
                            <i class="bi bi-cash-coin me-1"></i> Record payment
                        </a>
                    </div>
                @endcan

                @if ($payments->isEmpty())
                    <div class="empty-state">
                        <i class="bi bi-cash-coin"></i>
                        <p class="mb-0 mt-2">No payments recorded yet.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-app table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Reference</th><th>Date</th><th>Method</th>
                                    <th class="text-end">Amount</th><th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($payments as $payment)
                                    <tr>
                                        <td>
                                            <a href="{{ route('payments.show', $payment) }}" class="text-decoration-none">
                                                <code class="small">{{ $payment->payment_reference }}</code>
                                            </a>
                                        </td>
                                        <td class="small">{{ $payment->payment_date->format('d M Y') }}</td>
                                        <td class="small">{{ $payment->payment_method->label() }}</td>
                                        <td class="text-end small">&#8369;{{ number_format((float) $payment->amount, 2) }}</td>
                                        <td>
                                            <span class="badge {{ $payment->status->badgeClass() }}">
                                                {{ $payment->status->label() }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Service history --}}
            <div class="tab-pane fade" id="tab-history" role="tabpanel">
                @forelse ($customer->serviceStatusLogs->sortByDesc('created_at') as $log)
                    <div class="d-flex gap-3 border-bottom py-2 small">
                        <div class="text-secondary" style="min-width:9rem;">
                            {{ $log->created_at->format('d M Y, g:i A') }}
                        </div>
                        <div>
                            <span class="fw-medium">{{ $log->from_status ?? 'new' }} &rarr; {{ $log->to_status }}</span>
                            @if ($log->reason)<div class="text-secondary">{{ $log->reason }}</div>@endif
                            <div class="text-secondary">
                                {{ $log->is_automatic ? 'Automatic' : ($log->changedBy?->full_name ?? 'System') }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <i class="bi bi-clock-history"></i>
                        <p class="mb-0 mt-2">No service status changes recorded.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
