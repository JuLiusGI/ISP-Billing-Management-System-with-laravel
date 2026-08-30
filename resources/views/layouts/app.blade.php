<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ $isp['name'] }}</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body class="app-body">

{{--
    The sidebar puts dozens of links before the content on every page. Without
    this, reaching the page itself by keyboard means tabbing past all of them,
    every time.
--}}
<a class="skip-link" href="#main-content">Skip to main content</a>

<div class="app-shell">

    {{-- Sidebar --------------------------------------------------------- --}}
    <aside class="app-sidebar" id="appSidebar">
        <div class="app-sidebar__brand">
            <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 text-white text-decoration-none">
                <i class="bi bi-router-fill fs-4 text-danger"></i>
                <span class="fw-semibold">{{ $isp['name'] }}</span>
            </a>
            <button class="btn btn-sm btn-link text-white-50 d-lg-none p-0" type="button"
                    data-sidebar-close aria-label="Close menu">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <nav class="app-sidebar__nav" aria-label="Main navigation">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                       href="{{ route('dashboard') }}">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
            </ul>

            @can('customers.view')
                <div class="app-sidebar__heading">Customers</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('customers.index') && ! request()->hasAny(['status', 'archived']) ? 'active' : '' }}"
                           href="{{ route('customers.index') }}">
                            <i class="bi bi-people"></i> All Customers
                        </a>
                    </li>
                    @can('customers.create')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('customers.create') ? 'active' : '' }}"
                               href="{{ route('customers.create') }}">
                                <i class="bi bi-person-plus"></i> Add Customer
                            </a>
                        </li>
                    @endcan
                    @foreach ([
                        'active' => ['Active Customers', 'person-check'],
                        'inactive' => ['Inactive Customers', 'person-dash'],
                        'suspended' => ['Suspended Customers', 'person-exclamation'],
                    ] as $value => [$label, $icon])
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('customers.index') && request('status') === $value ? 'active' : '' }}"
                               href="{{ route('customers.index', ['status' => $value]) }}">
                                <i class="bi bi-{{ $icon }}"></i> {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endcan

            @canany(['billing.view', 'invoices.view'])
                <div class="app-sidebar__heading">Billing</div>
                <ul class="nav flex-column">
                    @can('invoices.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('invoices.*') && ! request()->hasAny(['view', 'status']) ? 'active' : '' }}"
                               href="{{ route('invoices.index') }}">
                                <i class="bi bi-receipt"></i> Invoices
                            </a>
                        </li>
                    @endcan
                    @can('invoices.create')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('invoices.create') ? 'active' : '' }}"
                               href="{{ route('invoices.create') }}">
                                <i class="bi bi-file-earmark-plus"></i> Create Invoice
                            </a>
                        </li>
                    @endcan
                    @can('invoices.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request('view') === 'outstanding' ? 'active' : '' }}"
                               href="{{ route('invoices.index', ['view' => 'outstanding']) }}">
                                <i class="bi bi-hourglass-split"></i> Unpaid Invoices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request('view') === 'overdue' ? 'active' : '' }}"
                               href="{{ route('invoices.index', ['view' => 'overdue']) }}">
                                <i class="bi bi-exclamation-triangle"></i> Overdue Invoices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request('status') === 'paid' ? 'active' : '' }}"
                               href="{{ route('invoices.index', ['status' => 'paid']) }}">
                                <i class="bi bi-check2-circle"></i> Paid Invoices
                            </a>
                        </li>
                    @endcan
                    @can('billing.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('billing.*') ? 'active' : '' }}"
                               href="{{ route('billing.index') }}">
                                <i class="bi bi-calendar3"></i> Billing Cycles
                            </a>
                        </li>
                    @endcan
                </ul>
            @endcanany

            @can('payments.view')
                <div class="app-sidebar__heading">Payments</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('payments.index') && ! request()->hasAny(['from', 'to']) ? 'active' : '' }}"
                           href="{{ route('payments.index') }}">
                            <i class="bi bi-cash-coin"></i> Payments
                        </a>
                    </li>
                    @can('payments.create')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('payments.create') ? 'active' : '' }}"
                               href="{{ route('payments.create') }}">
                                <i class="bi bi-plus-circle"></i> Record Payment
                            </a>
                        </li>
                    @endcan
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('payments.index') && request('from') ? 'active' : '' }}"
                           href="{{ route('payments.index', ['from' => now()->startOfMonth()->toDateString()]) }}">
                            <i class="bi bi-clock-history"></i> Payment History
                        </a>
                    </li>
                    @can('receipts.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('receipts.*') ? 'active' : '' }}"
                               href="{{ route('receipts.index') }}">
                                <i class="bi bi-receipt-cutoff"></i> Receipts
                            </a>
                        </li>
                    @endcan
                </ul>
            @endcan

            @canany(['plans.view', 'subscriptions.view'])
                <div class="app-sidebar__heading">Internet Services</div>
                <ul class="nav flex-column">
                    @can('plans.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('plans.*') ? 'active' : '' }}"
                               href="{{ route('plans.index') }}">
                                <i class="bi bi-diagram-3"></i> Internet Plans
                            </a>
                        </li>
                    @endcan
                    @can('subscriptions.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('subscriptions.*') && ! request('status') ? 'active' : '' }}"
                               href="{{ route('subscriptions.index') }}">
                                <i class="bi bi-wifi"></i> Customer Subscriptions
                            </a>
                        </li>

                        @foreach ([
                            App\Enums\SubscriptionStatus::Active,
                            App\Enums\SubscriptionStatus::Suspended,
                            App\Enums\SubscriptionStatus::Expired,
                        ] as $serviceStatus)
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('services.index') && (request('status', 'active') === $serviceStatus->value) ? 'active' : '' }}"
                                   href="{{ route('services.index', ['status' => $serviceStatus->value]) }}">
                                    <i class="bi bi-{{ $serviceStatus->icon() }}"></i>
                                    {{ $serviceStatus->label() }} Services
                                </a>
                            </li>
                        @endforeach

                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('services.history') ? 'active' : '' }}"
                               href="{{ route('services.history') }}">
                                <i class="bi bi-clock-history"></i> Service History
                            </a>
                        </li>
                    @endcan
                </ul>
            @endcanany

            @can('expenses.view')
                <div class="app-sidebar__heading">Finance</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}"
                           href="{{ route('expenses.index') }}">
                            <i class="bi bi-wallet2"></i> Expenses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('expense-categories.*') ? 'active' : '' }}"
                           href="{{ route('expense-categories.index') }}">
                            <i class="bi bi-tags"></i> Expense Categories
                        </a>
                    </li>
                </ul>
            @endcan

            @can('reports.view')
                <div class="app-sidebar__heading">Reports</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('reports.index') ? 'active' : '' }}"
                           href="{{ route('reports.index') }}">
                            <i class="bi bi-bar-chart"></i> All Reports
                        </a>
                    </li>
                    @foreach ([
                        ['reports.financial', 'reports.summary', 'Financial Summary', 'calculator'],
                        ['reports.financial', 'reports.revenue', 'Revenue Reports', 'graph-up-arrow'],
                        ['payments.view', 'reports.payments', 'Payment Reports', 'cash-coin'],
                        ['invoices.view', 'reports.billing', 'Billing Reports', 'receipt'],
                        ['invoices.view', 'reports.overdue', 'Overdue Reports', 'exclamation-triangle'],
                        ['expenses.view', 'reports.expenses', 'Expense Reports', 'wallet2'],
                        ['reports.operational', 'reports.customers', 'Customer Reports', 'people'],
                        ['reports.operational', 'reports.services', 'Service Reports', 'hdd-network'],
                    ] as [$ability, $routeName, $label, $icon])
                        @can($ability)
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs($routeName) ? 'active' : '' }}"
                                   href="{{ route($routeName) }}">
                                    <i class="bi bi-{{ $icon }}"></i> {{ $label }}
                                </a>
                            </li>
                        @endcan
                    @endforeach
                </ul>
            @endcan

            @canany(['users.view', 'roles.view', 'audit_logs.view', 'settings.view'])
                <div class="app-sidebar__heading">Administration</div>
                <ul class="nav flex-column">
                    @can('users.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}"
                               href="{{ route('users.index') }}">
                                <i class="bi bi-people"></i> Users
                            </a>
                        </li>
                    @endcan
                    @can('roles.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}"
                               href="{{ route('roles.index') }}">
                                <i class="bi bi-shield-lock"></i> Roles &amp; permissions
                            </a>
                        </li>
                    @endcan
                    @can('audit_logs.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}"
                               href="{{ route('audit-logs.index') }}">
                                <i class="bi bi-journal-text"></i> Audit Logs
                            </a>
                        </li>
                    @endcan
                    @can('settings.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}"
                               href="{{ route('settings.index') }}">
                                <i class="bi bi-sliders"></i> System Settings
                            </a>
                        </li>
                    @endcan
                </ul>
            @endcanany
        </nav>

        <div class="app-sidebar__footer small">
            <div class="text-white-50">Signed in as</div>
            <div class="text-white text-truncate">{{ auth()->user()->full_name }}</div>
        </div>
    </aside>

    <div class="app-sidebar__backdrop" data-sidebar-close></div>

    {{-- Main ------------------------------------------------------------ --}}
    <div class="app-main">
        <header class="app-topbar">
            <button class="btn btn-link text-dark d-lg-none p-0 me-2" type="button"
                    data-sidebar-open aria-label="Open menu">
                <i class="bi bi-list fs-4"></i>
            </button>

            <h1 class="app-topbar__title h6 mb-0">@yield('title', 'Dashboard')</h1>

            <div class="ms-auto dropdown">
                <button class="btn btn-sm btn-light border d-flex align-items-center gap-2 dropdown-toggle"
                        type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="app-avatar">{{ auth()->user()->initials }}</span>
                    <span class="d-none d-sm-inline">{{ auth()->user()->full_name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                        <span class="dropdown-item-text small text-secondary">
                            {{ auth()->user()->roles->pluck('display_name')->join(', ') ?: 'No role assigned' }}
                        </span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="bi bi-person-gear me-2"></i>My profile
                        </a>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Sign out
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        @hasSection('breadcrumb')
            <nav class="app-breadcrumb" aria-label="Breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    @yield('breadcrumb')
                </ol>
            </nav>
        @endif

        <main class="app-content" id="main-content" tabindex="-1">
            @yield('content')
        </main>

        <footer class="app-footer small text-secondary">
            &copy; {{ date('Y') }} {{ $isp['name'] }}
        </footer>
    </div>
</div>

<x-toasts />

<script>
    // Off-canvas sidebar for narrow screens.
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-sidebar-open]')) {
            document.body.classList.add('sidebar-open');
        }
        if (event.target.closest('[data-sidebar-close]')) {
            document.body.classList.remove('sidebar-open');
        }
    });

    // Close the mobile sidebar on Escape, the same as any other overlay.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.body.classList.remove('sidebar-open');
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        // Confirmation for destructive submits.
        const message = form.dataset.confirm;
        if (message && !window.confirm(message)) {
            event.preventDefault();
            return;
        }

        /*
         * Busy state. A billing run or a report over a year is slow enough that
         * an unchanged button reads as a click that did not register, and the
         * second click submits the form twice.
         *
         * Skipped for GET forms — filters return fast, and leaving the button
         * spinning after a back-navigation restores the page from cache would
         * look broken.
         */
        if ((form.method || '').toLowerCase() === 'get') {
            return;
        }

        // Guard the second submit on the form rather than by disabling the
        // button. A disabled submit button is dropped from the request, and
        // some of ours carry the name and value the action depends on — the
        // service status buttons post which status was chosen that way.
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = 'true';

        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.setAttribute('aria-busy', 'true');
        });
    });

    // A page restored from the back/forward cache must not stay busy.
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submitting]').forEach((form) => {
            delete form.dataset.submitting;
        });
        document.querySelectorAll('[aria-busy="true"]').forEach((button) => {
            button.removeAttribute('aria-busy');
        });
    });
</script>
@stack('scripts')
</body>
</html>
