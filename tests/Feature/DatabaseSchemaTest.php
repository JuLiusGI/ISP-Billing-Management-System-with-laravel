<?php

namespace Tests\Feature;

use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_table_required_by_the_specification_exists(): void
    {
        $expected = [
            'users', 'roles', 'permissions', 'role_user', 'permission_role',
            'customers', 'customer_contacts', 'customer_addresses',
            'internet_plans', 'subscriptions',
            'billing_cycles', 'invoices', 'invoice_items',
            'payments', 'payment_allocations', 'receipts',
            'service_status_logs',
            'expenses', 'expense_categories',
            'notifications', 'audit_logs', 'system_settings',
        ];

        foreach ($expected as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table [{$table}].");
        }
    }

    public function test_all_monetary_columns_use_decimal(): void
    {
        $offenders = DB::table('information_schema.columns')
            ->select('table_name', 'column_name', 'data_type')
            ->where('table_schema', DB::getDatabaseName())
            ->whereIn('table_name', [
                'internet_plans', 'subscriptions', 'invoices', 'invoice_items',
                'payments', 'payment_allocations', 'expenses',
            ])
            ->whereIn('column_name', [
                'monthly_price', 'installation_fee', 'activation_fee', 'monthly_rate',
                'discount_amount', 'subtotal', 'discount_total', 'charges_total',
                'tax_total', 'total_amount', 'amount_paid', 'balance_due',
                'unit_price', 'line_total', 'amount', 'allocated_amount', 'quantity',
            ])
            ->where('data_type', '<>', 'decimal')
            ->get();

        $this->assertCount(
            0,
            $offenders,
            'Financial columns must be DECIMAL, never floating point: '.$offenders->toJson()
        );
    }

    public function test_a_subscription_cannot_be_invoiced_twice_for_the_same_period(): void
    {
        $ids = $this->seedBillableCustomer();

        $this->insertInvoice('INV-DUP-1', $ids, '2026-08-01');

        $this->expectException(QueryException::class);

        $this->insertInvoice('INV-DUP-2', $ids, '2026-08-01');
    }

    public function test_ad_hoc_invoices_without_a_subscription_are_not_blocked(): void
    {
        $ids = $this->seedBillableCustomer();
        $ids['subscription'] = null;

        $this->insertInvoice('INV-ADHOC-1', $ids, '2026-08-01');
        $this->insertInvoice('INV-ADHOC-2', $ids, '2026-08-01');

        $this->assertSame(2, DB::table('invoices')->count());
    }

    public function test_a_customer_with_invoices_cannot_be_deleted(): void
    {
        $ids = $this->seedBillableCustomer();
        $this->insertInvoice('INV-FK-1', $ids, '2026-08-01');

        $this->expectException(QueryException::class);

        DB::table('customers')->where('id', $ids['customer'])->delete();
    }

    public function test_reference_seeders_can_be_run_repeatedly(): void
    {
        $seeders = [RoleAndPermissionSeeder::class, ExpenseCategorySeeder::class, SystemSettingSeeder::class];

        $this->seed($seeders);

        $first = $this->referenceCounts();

        $this->seed($seeders);

        $this->assertSame($first, $this->referenceCounts(), 'Seeders are not idempotent.');
    }

    public function test_reseeding_preserves_a_setting_an_administrator_changed(): void
    {
        $this->seed(SystemSettingSeeder::class);

        DB::table('system_settings')
            ->where('key', 'billing.invoice_prefix')
            ->update(['value' => 'CUSTOM']);

        $this->seed(SystemSettingSeeder::class);

        $this->assertSame(
            'CUSTOM',
            DB::table('system_settings')->where('key', 'billing.invoice_prefix')->value('value')
        );
    }

    /** @return array{customer: int, plan: int, subscription: int|null} */
    private function seedBillableCustomer(): array
    {
        $customer = DB::table('customers')->insertGetId([
            'account_number' => 'ACC-'.fake()->unique()->numerify('######'),
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'contact_number' => '09171234567',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $plan = DB::table('internet_plans')->insertGetId([
            'plan_code' => 'PLAN-'.fake()->unique()->numerify('####'),
            'name' => 'Test Plan',
            'download_speed' => 50,
            'upload_speed' => 50,
            'monthly_price' => 1499.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscription = DB::table('subscriptions')->insertGetId([
            'subscription_code' => 'SUB-'.fake()->unique()->numerify('#####'),
            'customer_id' => $customer,
            'internet_plan_id' => $plan,
            'start_date' => '2026-08-01',
            'monthly_rate' => 1499.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['customer' => $customer, 'plan' => $plan, 'subscription' => $subscription];
    }

    /** @param array{customer: int, plan: int, subscription: int|null} $ids */
    private function insertInvoice(string $number, array $ids, string $periodStart): void
    {
        DB::table('invoices')->insert([
            'invoice_number' => $number,
            'customer_id' => $ids['customer'],
            'subscription_id' => $ids['subscription'],
            'billing_period_start' => $periodStart,
            'invoice_date' => $periodStart,
            'due_date' => '2026-08-15',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, int> */
    private function referenceCounts(): array
    {
        return [
            'roles' => DB::table('roles')->count(),
            'permissions' => DB::table('permissions')->count(),
            'permission_role' => DB::table('permission_role')->count(),
            'expense_categories' => DB::table('expense_categories')->count(),
            'system_settings' => DB::table('system_settings')->count(),
        ];
    }
}
