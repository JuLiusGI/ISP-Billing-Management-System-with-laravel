<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the default expense categories from MASTER_SPEC §18.
 *
 * Categories are configurable, so these are starting points rather than a
 * fixed list. Existing rows are left in place.
 */
class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $categories = [
            ['code' => 'UPSTREAM', 'name' => 'Internet Upstream', 'description' => 'Bandwidth purchased from upstream providers.'],
            ['code' => 'ELECTRICITY', 'name' => 'Electricity', 'description' => 'Power for network equipment and offices.'],
            ['code' => 'EQUIPMENT', 'name' => 'Equipment', 'description' => 'Routers, switches, ONUs, cabling and hardware.'],
            ['code' => 'MAINTENANCE', 'name' => 'Maintenance', 'description' => 'Repairs and preventive maintenance.'],
            ['code' => 'SALARIES', 'name' => 'Salaries', 'description' => 'Staff compensation and benefits.'],
            ['code' => 'TRANSPORT', 'name' => 'Transportation', 'description' => 'Fuel and vehicle costs for field work.'],
            ['code' => 'SUPPLIES', 'name' => 'Office Supplies', 'description' => 'Consumables and office materials.'],
            ['code' => 'OTHER', 'name' => 'Other', 'description' => 'Expenses that do not fit another category.'],
        ];

        DB::table('expense_categories')->upsert(
            collect($categories)->map(fn (array $c) => $c + [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
            ['code'],
            ['name', 'description', 'updated_at']
        );
    }
}
