<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

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
        ]);
    }
}
