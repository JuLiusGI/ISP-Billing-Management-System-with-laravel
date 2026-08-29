<?php

namespace Database\Seeders;

use App\Enums\PlanBillingCycle;
use App\Enums\SpeedUnit;
use App\Models\InternetPlan;
use Illuminate\Database\Seeder;

/**
 * Starting plans for a fresh installation.
 *
 * These are examples, not fixtures: nothing in the application refers to them,
 * and an administrator is expected to edit or replace them. Skipped entirely
 * once any plan exists.
 */
class InternetPlanSeeder extends Seeder
{
    public function run(): void
    {
        if (InternetPlan::withTrashed()->exists()) {
            return;
        }

        $plans = [
            ['HOME-50', 'Home 50 Mbps', 50, 50, 999.00, 1500.00, 'Entry-level fibre for light browsing and streaming.'],
            ['HOME-100', 'Home 100 Mbps', 100, 100, 1499.00, 1500.00, 'Everyday fibre for families and remote work.'],
            ['HOME-200', 'Home 200 Mbps', 200, 200, 1999.00, 1500.00, 'Higher speed for heavy streaming and gaming.'],
            ['BIZ-300', 'Business 300 Mbps', 300, 300, 3499.00, 2500.00, 'Business fibre with priority support.'],
            ['BIZ-500', 'Business 500 Mbps', 500, 500, 4999.00, 2500.00, 'High-capacity business fibre.'],
        ];

        foreach ($plans as [$code, $name, $down, $up, $price, $install, $description]) {
            InternetPlan::create([
                'plan_code' => $code,
                'name' => $name,
                'download_speed' => $down,
                'upload_speed' => $up,
                'speed_unit' => SpeedUnit::Mbps,
                'monthly_price' => $price,
                'installation_fee' => $install,
                'activation_fee' => 0,
                'billing_cycle' => PlanBillingCycle::Monthly,
                'description' => $description,
                'is_active' => true,
            ]);
        }
    }
}
