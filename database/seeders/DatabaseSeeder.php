<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Deliberately NOT using WithoutModelEvents. Customers and subscriptions
    // generate their account number and code in a creating hook, and muting
    // model events would leave those columns unset.

    /**
     * Seeds the reference data the application needs in order to run.
     *
     * Every seeder below is idempotent, so `db:seed` can be re-run without
     * duplicating rows or overwriting configuration an administrator changed.
     *
     * Demo data (customers, subscriptions, invoices, payments) arrives with
     * the modules that own it.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            ExpenseCategorySeeder::class,
            SystemSettingSeeder::class,
            // Depends on RoleAndPermissionSeeder having run first.
            UserSeeder::class,
            // Starting data. Each skips itself if its table is already populated.
            InternetPlanSeeder::class,
            CustomerSeeder::class,
            // Depends on plans and customers already existing.
            SubscriptionSeeder::class,
        ]);
    }
}
