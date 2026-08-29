<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the configurable defaults from MASTER_SPEC §28.
 *
 * Nothing in the application should hard-code these values; they are read
 * through the settings layer so an administrator can change them at runtime.
 * Existing values are preserved so re-seeding never clobbers configuration.
 */
class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $settings = [
            // Company
            ['company', 'company.name', 'ISP Billing', 'string', 'ISP name'],
            ['company', 'company.logo_path', null, 'string', 'Logo'],
            ['company', 'company.address', null, 'string', 'Business address'],
            ['company', 'company.phone', null, 'string', 'Contact number'],
            ['company', 'company.email', null, 'string', 'Contact email'],
            ['company', 'company.website', null, 'string', 'Website'],

            // Billing
            ['billing', 'billing.default_cycle', 'monthly', 'string', 'Default billing cycle'],
            ['billing', 'billing.grace_period_days', '5', 'integer', 'Default grace period (days)'],
            ['billing', 'billing.invoice_prefix', 'INV', 'string', 'Invoice number prefix'],
            ['billing', 'billing.receipt_prefix', 'OR', 'string', 'Receipt number prefix'],
            ['billing', 'billing.currency', 'PHP', 'string', 'Default currency'],
            ['billing', 'billing.currency_symbol', '₱', 'string', 'Currency symbol'],
            ['billing', 'billing.tax_enabled', '0', 'boolean', 'Tax enabled'],
            ['billing', 'billing.tax_rate', '12.00', 'decimal', 'Tax rate (%)'],

            // Service
            ['service', 'service.auto_suspend_enabled', '0', 'boolean', 'Automatic suspension enabled'],
            ['service', 'service.suspend_after_days_overdue', '15', 'integer', 'Suspend after N days overdue'],
            ['service', 'service.default_status', 'pending', 'string', 'Default service status'],

            // Notifications
            ['notifications', 'notifications.email_enabled', '0', 'boolean', 'Email notifications enabled'],
            ['notifications', 'notifications.on_invoice_created', '1', 'boolean', 'Notify on new invoice'],
            ['notifications', 'notifications.on_payment_received', '1', 'boolean', 'Notify on payment received'],
            ['notifications', 'notifications.on_invoice_overdue', '1', 'boolean', 'Notify on overdue invoice'],
        ];

        $rows = collect($settings)->map(fn (array $s) => [
            'group' => $s[0],
            'key' => $s[1],
            'value' => $s[2],
            'type' => $s[3],
            'label' => $s[4],
            'description' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        // Only the metadata is refreshed on re-run; `value` is deliberately
        // excluded so an administrator's saved configuration survives seeding.
        DB::table('system_settings')->upsert($rows, ['key'], ['group', 'type', 'label', 'updated_at']);
    }
}
