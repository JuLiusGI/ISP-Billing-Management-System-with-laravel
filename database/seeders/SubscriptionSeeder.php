<?php

namespace Database\Seeders;

use App\Enums\CustomerStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\ServiceStatusLog;
use App\Models\Subscription;
use Illuminate\Database\Seeder;

/**
 * Gives the sample customers a subscription each, with a status that matches
 * the customer's own. Skipped once any subscription exists.
 */
class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        if (Subscription::withTrashed()->exists()) {
            return;
        }

        $plans = InternetPlan::active()->get();

        if ($plans->isEmpty()) {
            return;
        }

        Customer::query()->each(function (Customer $customer) use ($plans): void {
            $plan = $plans->random();

            $status = match ($customer->status) {
                CustomerStatus::Active => SubscriptionStatus::Active,
                CustomerStatus::Suspended => SubscriptionStatus::Suspended,
                CustomerStatus::PendingInstallation => SubscriptionStatus::Pending,
                default => SubscriptionStatus::Expired,
            };

            $activated = $status === SubscriptionStatus::Pending
                ? null
                : ($customer->installation_date ?? now()->subMonths(3));

            $subscription = Subscription::create([
                'customer_id' => $customer->id,
                'internet_plan_id' => $plan->id,
                'start_date' => $customer->installation_date ?? now()->subMonths(3),
                'activation_date' => $activated,
                'billing_day' => fake()->numberBetween(1, 28),
                // Copied from the plan, exactly as the application does.
                'monthly_rate' => $plan->monthly_price,
                'installation_fee' => $plan->installation_fee,
                'discount_amount' => 0,
                'status' => $status,
                'connection_type' => 'fiber',
                'username' => str($customer->account_number)->lower()->append('@isp')->value(),
            ]);

            ServiceStatusLog::create([
                'subscription_id' => $subscription->id,
                'customer_id' => $customer->id,
                'from_status' => null,
                'to_status' => $status->value,
                'reason' => 'Seeded sample data',
                'is_automatic' => false,
            ]);
        });
    }
}
