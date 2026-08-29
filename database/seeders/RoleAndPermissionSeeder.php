<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the role and permission catalogue.
 *
 * This is reference data the application depends on, not sample data, so the
 * seeder is idempotent and safe to re-run. Enforcement of these abilities is
 * wired up in a later phase; this only establishes the vocabulary.
 */
class RoleAndPermissionSeeder extends Seeder
{
    /** @var array<string, array{label: string, module: string}> */
    private array $permissions = [
        'dashboard.view' => ['label' => 'View dashboard', 'module' => 'Dashboard'],

        'customers.view' => ['label' => 'View customers', 'module' => 'Customers'],
        'customers.create' => ['label' => 'Create customers', 'module' => 'Customers'],
        'customers.update' => ['label' => 'Update customers', 'module' => 'Customers'],
        'customers.delete' => ['label' => 'Delete customers', 'module' => 'Customers'],

        'plans.view' => ['label' => 'View internet plans', 'module' => 'Internet Plans'],
        'plans.create' => ['label' => 'Create internet plans', 'module' => 'Internet Plans'],
        'plans.update' => ['label' => 'Update internet plans', 'module' => 'Internet Plans'],
        'plans.delete' => ['label' => 'Delete internet plans', 'module' => 'Internet Plans'],

        'subscriptions.view' => ['label' => 'View subscriptions', 'module' => 'Subscriptions'],
        'subscriptions.create' => ['label' => 'Create subscriptions', 'module' => 'Subscriptions'],
        'subscriptions.update' => ['label' => 'Update subscriptions', 'module' => 'Subscriptions'],
        'subscriptions.manage_status' => ['label' => 'Change service status', 'module' => 'Subscriptions'],

        'billing.view' => ['label' => 'View billing cycles', 'module' => 'Billing'],
        'billing.generate' => ['label' => 'Generate invoices', 'module' => 'Billing'],
        'invoices.view' => ['label' => 'View invoices', 'module' => 'Billing'],
        'invoices.create' => ['label' => 'Create invoices', 'module' => 'Billing'],
        'invoices.update' => ['label' => 'Update invoices', 'module' => 'Billing'],
        'invoices.cancel' => ['label' => 'Cancel invoices', 'module' => 'Billing'],

        'payments.view' => ['label' => 'View payments', 'module' => 'Payments'],
        'payments.create' => ['label' => 'Record payments', 'module' => 'Payments'],
        'payments.reverse' => ['label' => 'Reverse payments', 'module' => 'Payments'],
        'receipts.view' => ['label' => 'View receipts', 'module' => 'Payments'],
        'receipts.issue' => ['label' => 'Issue receipts', 'module' => 'Payments'],

        'expenses.view' => ['label' => 'View expenses', 'module' => 'Expenses'],
        'expenses.create' => ['label' => 'Create expenses', 'module' => 'Expenses'],
        'expenses.update' => ['label' => 'Update expenses', 'module' => 'Expenses'],
        'expenses.delete' => ['label' => 'Delete expenses', 'module' => 'Expenses'],

        'reports.view' => ['label' => 'View reports', 'module' => 'Reports'],
        'reports.financial' => ['label' => 'View financial reports', 'module' => 'Reports'],
        'reports.operational' => ['label' => 'View operational reports', 'module' => 'Reports'],

        'users.view' => ['label' => 'View users', 'module' => 'Administration'],
        'users.create' => ['label' => 'Create users', 'module' => 'Administration'],
        'users.update' => ['label' => 'Update users', 'module' => 'Administration'],
        'users.delete' => ['label' => 'Delete users', 'module' => 'Administration'],
        'roles.view' => ['label' => 'View roles', 'module' => 'Administration'],
        'roles.manage' => ['label' => 'Manage roles and permissions', 'module' => 'Administration'],
        'audit_logs.view' => ['label' => 'View audit logs', 'module' => 'Administration'],
        'settings.view' => ['label' => 'View system settings', 'module' => 'Administration'],
        'settings.update' => ['label' => 'Update system settings', 'module' => 'Administration'],
    ];

    /** @var array<string, array{label: string, description: string, abilities: string[]|string}> */
    private array $roles = [
        'super-admin' => [
            'label' => 'Super Admin',
            'description' => 'Unrestricted access to every part of the system.',
            'abilities' => '*',
        ],
        'administrator' => [
            'label' => 'Administrator',
            'description' => 'Management access. Cannot redefine roles and permissions.',
            'abilities' => '*except:roles.manage',
        ],
        'billing-staff' => [
            'label' => 'Billing Staff',
            'description' => 'Handles customers, invoicing, payments and receipts.',
            'abilities' => [
                'dashboard.view',
                'customers.view', 'customers.create', 'customers.update',
                'plans.view',
                'subscriptions.view', 'subscriptions.create', 'subscriptions.update',
                'billing.view', 'billing.generate',
                'invoices.view', 'invoices.create', 'invoices.update', 'invoices.cancel',
                'payments.view', 'payments.create',
                'receipts.view', 'receipts.issue',
                'reports.view',
            ],
        ],
        'technician' => [
            'label' => 'Technician',
            'description' => 'Field and service access. No financial abilities.',
            'abilities' => [
                'dashboard.view',
                'customers.view',
                'plans.view',
                'subscriptions.view', 'subscriptions.update', 'subscriptions.manage_status',
                'reports.view', 'reports.operational',
            ],
        ],
        'accountant' => [
            'label' => 'Accountant',
            'description' => 'Revenue, expenses and financial reporting.',
            'abilities' => [
                'dashboard.view',
                'customers.view',
                'invoices.view',
                'payments.view', 'payments.create', 'payments.reverse',
                'receipts.view',
                'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete',
                'reports.view', 'reports.financial',
            ],
        ],
    ];

    public function run(): void
    {
        $now = now();

        DB::table('permissions')->upsert(
            collect($this->permissions)->map(fn (array $meta, string $name) => [
                'name' => $name,
                'display_name' => $meta['label'],
                'module' => $meta['module'],
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all(),
            ['name'],
            ['display_name', 'module', 'updated_at']
        );

        DB::table('roles')->upsert(
            collect($this->roles)->map(fn (array $meta, string $name) => [
                'name' => $name,
                'display_name' => $meta['label'],
                'description' => $meta['description'],
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all(),
            ['name'],
            ['display_name', 'description', 'is_system', 'updated_at']
        );

        $permissionIds = DB::table('permissions')->pluck('id', 'name');
        $roleIds = DB::table('roles')->pluck('id', 'name');

        foreach ($this->roles as $roleName => $meta) {
            $abilities = $this->resolveAbilities($meta['abilities']);

            $rows = collect($abilities)
                ->map(fn (string $ability) => [
                    'permission_id' => $permissionIds[$ability],
                    'role_id' => $roleIds[$roleName],
                ])
                ->all();

            // Rewrite the role's grants so removing an ability from this seeder
            // actually revokes it rather than leaving a stale row behind.
            DB::table('permission_role')->where('role_id', $roleIds[$roleName])->delete();
            DB::table('permission_role')->insert($rows);
        }
    }

    /**
     * @param  string[]|string  $abilities
     * @return string[]
     */
    private function resolveAbilities(array|string $abilities): array
    {
        $all = array_keys($this->permissions);

        if ($abilities === '*') {
            return $all;
        }

        if (is_string($abilities) && str_starts_with($abilities, '*except:')) {
            $excluded = explode(',', substr($abilities, strlen('*except:')));

            return array_values(array_diff($all, $excluded));
        }

        return (array) $abilities;
    }
}
