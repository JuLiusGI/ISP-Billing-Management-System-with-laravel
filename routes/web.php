<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BillingCycleController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InternetPlanController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|
| "active" re-checks account status on every request, so suspending a user
| ends their session rather than waiting for it to expire.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    Route::controller(CustomerController::class)->prefix('customers')->name('customers.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:customers.view')->name('index');
        Route::get('create', 'create')->middleware('permission:customers.create')->name('create');
        Route::post('/', 'store')->middleware('permission:customers.create')->name('store');
        Route::get('{customer}', 'show')->middleware('permission:customers.view')->name('show');
        // Photos are personal data, so they are served through the policy
        // rather than off the public disk by URL alone.
        Route::get('{customer}/photo', 'photo')->middleware('permission:customers.view')->name('photo');
        Route::get('{customer}/edit', 'edit')->middleware('permission:customers.update')->name('edit');
        Route::put('{customer}', 'update')->middleware('permission:customers.update')->name('update');
        Route::delete('{customer}', 'destroy')->middleware('permission:customers.delete')->name('destroy');
        Route::post('{customer}/restore', 'restore')->middleware('permission:customers.delete')->name('restore');
    });

    Route::controller(InternetPlanController::class)->prefix('plans')->name('plans.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:plans.view')->name('index');
        Route::get('create', 'create')->middleware('permission:plans.create')->name('create');
        Route::post('/', 'store')->middleware('permission:plans.create')->name('store');
        Route::get('{plan}/edit', 'edit')->middleware('permission:plans.update')->name('edit');
        Route::put('{plan}', 'update')->middleware('permission:plans.update')->name('update');
        Route::patch('{plan}/toggle', 'toggle')->middleware('permission:plans.update')->name('toggle');
        Route::delete('{plan}', 'destroy')->middleware('permission:plans.delete')->name('destroy');
    });

    Route::controller(SubscriptionController::class)->prefix('subscriptions')->name('subscriptions.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:subscriptions.view')->name('index');
        Route::get('create', 'create')->middleware('permission:subscriptions.create')->name('create');
        Route::post('/', 'store')->middleware('permission:subscriptions.create')->name('store');
        Route::get('{subscription}', 'show')->middleware('permission:subscriptions.view')->name('show');
        Route::get('{subscription}/edit', 'edit')->middleware('permission:subscriptions.update')->name('edit');
        Route::put('{subscription}', 'update')->middleware('permission:subscriptions.update')->name('update');
        Route::patch('{subscription}/status', 'changeStatus')
            ->middleware('permission:subscriptions.manage_status')
            ->name('status');
    });

    // Service management: the operational side of the same subscriptions.
    Route::controller(ServiceController::class)->prefix('services')->name('services.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:subscriptions.view')->name('index');
        Route::get('history', 'history')->middleware('permission:subscriptions.view')->name('history');
    });

    Route::controller(BillingCycleController::class)->prefix('billing')->name('billing.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:billing.view')->name('index');
        Route::post('/', 'store')->middleware('permission:billing.generate')->name('store');
        Route::get('{cycle}', 'show')->middleware('permission:billing.view')->name('show');
        Route::post('{cycle}/generate', 'generate')->middleware('permission:billing.generate')->name('generate');
        Route::post('mark-overdue', 'markOverdue')->middleware('permission:billing.generate')->name('mark-overdue');
    });

    Route::controller(InvoiceController::class)->prefix('invoices')->name('invoices.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:invoices.view')->name('index');
        Route::get('create', 'create')->middleware('permission:invoices.create')->name('create');
        Route::post('/', 'store')->middleware('permission:invoices.create')->name('store');
        Route::get('{invoice}', 'show')->middleware('permission:invoices.view')->name('show');
        Route::get('{invoice}/print', 'print')->middleware('permission:invoices.view')->name('print');
        Route::get('{invoice}/edit', 'edit')->middleware('permission:invoices.update')->name('edit');
        Route::put('{invoice}', 'update')->middleware('permission:invoices.update')->name('update');
        Route::patch('{invoice}/cancel', 'cancel')->middleware('permission:invoices.cancel')->name('cancel');
    });

    Route::controller(PaymentController::class)->prefix('payments')->name('payments.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:payments.view')->name('index');
        Route::get('create', 'create')->middleware('permission:payments.create')->name('create');
        Route::post('/', 'store')->middleware('permission:payments.create')->name('store');
        Route::get('{payment}', 'show')->middleware('permission:payments.view')->name('show');
        Route::post('{payment}/allocate', 'allocate')->middleware('permission:payments.create')->name('allocate');
        Route::patch('{payment}/reverse', 'reverse')->middleware('permission:payments.reverse')->name('reverse');
    });

    Route::controller(ReceiptController::class)->prefix('receipts')->name('receipts.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:receipts.view')->name('index');
        Route::get('{receipt}', 'show')->middleware('permission:receipts.view')->name('show');
        Route::get('{receipt}/print', 'print')->middleware('permission:receipts.view')->name('print');
    });

    Route::post('payments/{payment}/receipt', [ReceiptController::class, 'store'])
        ->middleware('permission:receipts.issue')
        ->name('payments.receipt');

    /*
     * Reports. Each is gated on the ability covering the data it exposes, not
     * on one blanket reports ability, so a role only sees reports over records
     * it could already read.
     */
    Route::controller(ReportController::class)->prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:reports.view')->name('index');

        Route::get('revenue', 'revenue')->middleware('permission:reports.financial')->name('revenue');
        Route::get('summary', 'summary')->middleware('permission:reports.financial')->name('summary');
        Route::get('expenses', 'expenses')->middleware('permission:expenses.view')->name('expenses');
        Route::get('payments', 'payments')->middleware('permission:payments.view')->name('payments');
        Route::get('billing', 'billing')->middleware('permission:invoices.view')->name('billing');
        Route::get('outstanding', 'outstanding')->middleware('permission:invoices.view')->name('outstanding');
        Route::get('overdue', 'overdue')->middleware('permission:invoices.view')->name('overdue');

        Route::get('customers', 'customers')->middleware('permission:reports.operational')->name('customers');
        Route::get('services', 'services')->middleware('permission:reports.operational')->name('services');
    });

    // Finance
    Route::controller(ExpenseController::class)->prefix('expenses')->name('expenses.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:expenses.view')->name('index');
        Route::get('create', 'create')->middleware('permission:expenses.create')->name('create');
        Route::post('/', 'store')->middleware('permission:expenses.create')->name('store');
        Route::get('{expense}', 'show')->middleware('permission:expenses.view')->name('show');
        Route::get('{expense}/edit', 'edit')->middleware('permission:expenses.update')->name('edit');
        Route::put('{expense}', 'update')->middleware('permission:expenses.update')->name('update');
        Route::delete('{expense}', 'destroy')->middleware('permission:expenses.delete')->name('destroy');
        Route::post('{expense}/restore', 'restore')->middleware('permission:expenses.delete')->name('restore');
    });

    Route::controller(ExpenseCategoryController::class)
        ->prefix('expense-categories')
        ->name('expense-categories.')
        ->group(function (): void {
            Route::get('/', 'index')->middleware('permission:expenses.view')->name('index');
            Route::post('/', 'store')->middleware('permission:expenses.update')->name('store');
            Route::put('{category}', 'update')->middleware('permission:expenses.update')->name('update');
            Route::delete('{category}', 'destroy')->middleware('permission:expenses.update')->name('destroy');
        });

    // Administration. Abilities are enforced here as well as in the form
    // requests, so a hidden sidebar link is never the only thing stopping a
    // request from going through.
    Route::controller(UserController::class)->prefix('users')->name('users.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:users.view')->name('index');
        Route::get('create', 'create')->middleware('permission:users.create')->name('create');
        Route::post('/', 'store')->middleware('permission:users.create')->name('store');
        Route::get('{user}', 'show')->middleware('permission:users.view')->name('show');
        Route::get('{user}/edit', 'edit')->middleware('permission:users.update')->name('edit');
        Route::put('{user}', 'update')->middleware('permission:users.update')->name('update');
        Route::delete('{user}', 'destroy')->middleware('permission:users.delete')->name('destroy');
    });

    // Settings are saved one group at a time, so a careless save cannot change
    // company details, billing rules and suspension policy together.
    Route::controller(SettingController::class)->prefix('settings')->name('settings.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:settings.view')->name('index');
        Route::put('{group}', 'update')->middleware('permission:settings.update')->name('update');
    });

    // Read-only by design: the trail has no create, update or delete route.
    Route::controller(AuditLogController::class)->prefix('audit-logs')->name('audit-logs.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:audit_logs.view')->name('index');
        Route::get('{auditLog}', 'show')->middleware('permission:audit_logs.view')->name('show');
    });

    Route::controller(RoleController::class)->prefix('roles')->name('roles.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:roles.view')->name('index');
        Route::get('create', 'create')->middleware('permission:roles.manage')->name('create');
        Route::post('/', 'store')->middleware('permission:roles.manage')->name('store');
        Route::get('{role}/edit', 'edit')->middleware('permission:roles.manage')->name('edit');
        Route::put('{role}', 'update')->middleware('permission:roles.manage')->name('update');
        Route::delete('{role}', 'destroy')->middleware('permission:roles.manage')->name('destroy');
    });
});
