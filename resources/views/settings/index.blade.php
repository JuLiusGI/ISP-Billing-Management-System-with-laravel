@extends('layouts.app')

@section('title', 'System settings')
@section('breadcrumb')
    <li class="breadcrumb-item">Administration</li>
    <li class="breadcrumb-item active" aria-current="page">System settings</li>
@endsection

@php
    $readOnly = ! auth()->user()->can('settings.update');
@endphp

@section('content')
    <div class="mb-3">
        <h2 class="h5 mb-0 text-navy">System settings</h2>
        <p class="small text-secondary mb-0">
            Values the application reads at runtime. Nothing here is compiled in.
        </p>
    </div>

    @if ($readOnly)
        <div class="alert alert-secondary py-2 small" role="alert">
            <i class="bi bi-eye me-1"></i> You can view these settings but not change them.
        </div>
    @endif

    <div class="card border-0">
        <div class="card-header bg-white border-bottom pb-0">
            <ul class="nav nav-tabs card-header-tabs">
                @foreach ($groups as $key => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ $group === $key ? 'active' : '' }}"
                           href="{{ route('settings.index', ['group' => $key]) }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body">
            {{-- Company ---------------------------------------------------- --}}
            @if ($group === 'company')
                <form method="POST" action="{{ route('settings.update', 'company') }}"
                      enctype="multipart/form-data" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="company_name" class="form-label">ISP name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" id="company_name"
                                   class="form-control @error('company_name') is-invalid @enderror"
                                   value="{{ old('company_name', $company['name']) }}" @disabled($readOnly) required>
                            @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Shown in the interface and printed on invoices and receipts.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="company_email" class="form-label">Contact email</label>
                            <input type="email" name="company_email" id="company_email"
                                   class="form-control @error('company_email') is-invalid @enderror"
                                   value="{{ old('company_email', $company['email']) }}" @disabled($readOnly)>
                            @error('company_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="company_phone" class="form-label">Contact number</label>
                            <input type="text" name="company_phone" id="company_phone"
                                   class="form-control @error('company_phone') is-invalid @enderror"
                                   value="{{ old('company_phone', $company['phone']) }}" @disabled($readOnly)>
                            @error('company_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="company_website" class="form-label">Website</label>
                            <input type="text" name="company_website" id="company_website"
                                   class="form-control @error('company_website') is-invalid @enderror"
                                   value="{{ old('company_website', $company['website']) }}" @disabled($readOnly)>
                            @error('company_website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label for="company_address" class="form-label">Business address</label>
                            <input type="text" name="company_address" id="company_address"
                                   class="form-control @error('company_address') is-invalid @enderror"
                                   value="{{ old('company_address', $company['address']) }}" @disabled($readOnly)>
                            @error('company_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="logo" class="form-label">Logo</label>
                            <input type="file" name="logo" id="logo" accept="image/*"
                                   class="form-control @error('logo') is-invalid @enderror" @disabled($readOnly)>
                            @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">PNG, JPG, WEBP or SVG, up to 1&nbsp;MB.</div>
                        </div>

                        <div class="col-md-6 d-flex align-items-end">
                            @if ($company['logo'])
                                <div class="d-flex align-items-center gap-3">
                                    <img src="{{ Storage::url($company['logo']) }}" alt="Current logo"
                                         style="max-height:3rem;max-width:10rem;object-fit:contain;">
                                    @unless ($readOnly)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="remove_logo"
                                                   value="1" id="remove_logo">
                                            <label class="form-check-label small" for="remove_logo">Remove</label>
                                        </div>
                                    @endunless
                                </div>
                            @else
                                <p class="small text-secondary mb-2">No logo uploaded.</p>
                            @endif
                        </div>
                    </div>

                    @unless ($readOnly)
                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary">Save company settings</button>
                        </div>
                    @endunless
                </form>
            @endif

            {{-- Billing ---------------------------------------------------- --}}
            @if ($group === 'billing')
                <form method="POST" action="{{ route('settings.update', 'billing') }}" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="default_cycle" class="form-label">Default billing cycle</label>
                            <select name="default_cycle" id="default_cycle"
                                    class="form-select @error('default_cycle') is-invalid @enderror" @disabled($readOnly)>
                                @foreach (App\Enums\PlanBillingCycle::cases() as $cycle)
                                    <option value="{{ $cycle->value }}"
                                        @selected(old('default_cycle', $settings->defaultBillingCycle()) === $cycle->value)>
                                        {{ $cycle->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('default_cycle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="grace_period_days" class="form-label">Grace period (days)</label>
                            <input type="number" name="grace_period_days" id="grace_period_days" min="0" max="120"
                                   class="form-control @error('grace_period_days') is-invalid @enderror"
                                   value="{{ old('grace_period_days', $settings->gracePeriodDays()) }}" @disabled($readOnly)>
                            @error('grace_period_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Days between issue and due date.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="currency" class="form-label">Currency</label>
                            <div class="input-group">
                                <input type="text" name="currency" id="currency" maxlength="3"
                                       class="form-control @error('currency') is-invalid @enderror"
                                       value="{{ old('currency', $settings->currency()) }}" @disabled($readOnly)>
                                <input type="text" name="currency_symbol" maxlength="5" style="max-width:5rem;"
                                       class="form-control @error('currency_symbol') is-invalid @enderror"
                                       value="{{ old('currency_symbol', $settings->currencySymbol()) }}"
                                       aria-label="Currency symbol" @disabled($readOnly)>
                                @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @error('currency_symbol')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-text">Code and symbol.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="invoice_prefix" class="form-label">Invoice prefix</label>
                            <input type="text" name="invoice_prefix" id="invoice_prefix"
                                   class="form-control @error('invoice_prefix') is-invalid @enderror"
                                   value="{{ old('invoice_prefix', $settings->invoicePrefix()) }}" @disabled($readOnly)>
                            @error('invoice_prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Applies to invoices issued from now on.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="receipt_prefix" class="form-label">Receipt prefix</label>
                            <input type="text" name="receipt_prefix" id="receipt_prefix"
                                   class="form-control @error('receipt_prefix') is-invalid @enderror"
                                   value="{{ old('receipt_prefix', $settings->receiptPrefix()) }}" @disabled($readOnly)>
                            @error('receipt_prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="tax_rate" class="form-label">Tax rate (%)</label>
                            <input type="number" name="tax_rate" id="tax_rate" step="0.01" min="0" max="100"
                                   class="form-control @error('tax_rate') is-invalid @enderror"
                                   value="{{ old('tax_rate', $settings->taxRate()) }}" @disabled($readOnly)>
                            @error('tax_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <input type="hidden" name="tax_enabled" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="tax_enabled" value="1"
                                       id="tax_enabled" @checked(old('tax_enabled', $settings->taxEnabled())) @disabled($readOnly)>
                                <label class="form-check-label" for="tax_enabled">
                                    Apply tax to new invoices
                                    <span class="d-block small text-secondary">
                                        Existing invoices keep the tax they were issued with.
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    @unless ($readOnly)
                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary">Save billing settings</button>
                        </div>
                    @endunless
                </form>
            @endif

            {{-- Service ---------------------------------------------------- --}}
            @if ($group === 'service')
                <form method="POST" action="{{ route('settings.update', 'service') }}" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-12">
                            <input type="hidden" name="auto_suspend_enabled" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="auto_suspend_enabled" value="1"
                                       id="auto_suspend_enabled"
                                       @checked(old('auto_suspend_enabled', $settings->autoSuspendEnabled())) @disabled($readOnly)>
                                <label class="form-check-label" for="auto_suspend_enabled">
                                    Suspend overdue services automatically
                                    <span class="d-block small text-secondary">
                                        Off by default. Cutting a customer off without a person deciding to
                                        is not something an installation should start doing unasked.
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="suspend_after_days_overdue" class="form-label">Suspend after (days overdue)</label>
                            <input type="number" name="suspend_after_days_overdue" id="suspend_after_days_overdue"
                                   min="1" max="365"
                                   class="form-control @error('suspend_after_days_overdue') is-invalid @enderror"
                                   value="{{ old('suspend_after_days_overdue', $settings->suspendAfterDaysOverdue()) }}"
                                   @disabled($readOnly)>
                            @error('suspend_after_days_overdue')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="default_status" class="form-label">Default service status</label>
                            <select name="default_status" id="default_status"
                                    class="form-select @error('default_status') is-invalid @enderror" @disabled($readOnly)>
                                @foreach (['pending' => 'Pending', 'active' => 'Active'] as $value => $label)
                                    <option value="{{ $value }}"
                                        @selected(old('default_status', $settings->defaultServiceStatus()) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('default_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Status a new subscription starts in.</div>
                        </div>
                    </div>

                    <div class="alert alert-secondary small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        These values are read by the scheduled billing commands. The commands themselves
                        arrive with the automated billing phase.
                    </div>

                    @unless ($readOnly)
                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary">Save service settings</button>
                        </div>
                    @endunless
                </form>
            @endif

            {{-- Notifications ---------------------------------------------- --}}
            @if ($group === 'notifications')
                <form method="POST" action="{{ route('settings.update', 'notifications') }}" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <input type="hidden" name="email_enabled" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="email_enabled" value="1"
                                   id="email_enabled"
                                   @checked(old('email_enabled', $settings->emailNotificationsEnabled())) @disabled($readOnly)>
                            <label class="form-check-label fw-semibold" for="email_enabled">
                                Email notifications
                                <span class="d-block small text-secondary fw-normal">
                                    Master switch. With this off nothing below sends, whatever it is set to.
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="border-top pt-3">
                        @foreach ([
                            'on_invoice_created' => ['New invoice issued', 'Sent when an invoice is generated.'],
                            'on_payment_received' => ['Payment received', 'Acknowledges a payment and states the remaining balance.'],
                            'on_invoice_overdue' => ['Invoice overdue', 'Sent when an invoice passes its due date.'],
                            'on_service_suspended' => ['Service suspended', 'Sent when a line is cut.'],
                            'on_service_reactivated' => ['Service reactivated', 'Sent when a suspended line is restored.'],
                        ] as $key => [$label, $hint])
                            <input type="hidden" name="{{ $key }}" value="0">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="{{ $key }}" value="1"
                                       id="{{ $key }}"
                                       @checked(old($key, $settings->notifiesOn(str_replace('on_', '', $key)))) @disabled($readOnly)>
                                <label class="form-check-label" for="{{ $key }}">
                                    {{ $label }}
                                    <span class="d-block small text-secondary">{{ $hint }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <div class="alert alert-secondary small mt-3 mb-0">
                        <i class="bi bi-envelope me-1"></i>
                        Mail transport is configured with the <code>MAIL_*</code> environment variables, never here.
                        Notifications are queued, so <code>php artisan queue:work</code> must be running for
                        them to leave the application. A customer with no email address is skipped.
                    </div>

                    @unless ($readOnly)
                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary">Save notification settings</button>
                        </div>
                    @endunless
                </form>
            @endif
        </div>
    </div>
@endsection
