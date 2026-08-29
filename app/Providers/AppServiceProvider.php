<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\InternetPlanPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RolePolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\UserPolicy;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Shared for the lifetime of the request so a page that reads a dozen
        // settings runs one query rather than a dozen.
        $this->app->singleton(SettingsService::class);
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(InternetPlan::class, InternetPlanPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);

        /*
         * Resolves dot-namespaced abilities ("invoices.create") against the
         * signed-in user's granted permissions, so abilities need not be
         * registered one by one and no query runs at boot.
         *
         * Policy abilities are named without a dot (view, update, delete) and
         * deliberately fall through to their policy. A blanket super admin
         * grant here would skip guards that must hold for everyone, such as
         * the last super admin being undeletable. Policies still grant super
         * admins access, because hasPermission() returns true for them.
         *
         * Returning null rather than false lets anything unrecognised fall
         * through to a policy or an explicitly defined gate.
         */
        Gate::before(function (User $user, string $ability): ?bool {
            if (! str_contains($ability, '.')) {
                return null;
            }

            if ($user->isSuperAdmin()) {
                return true;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
