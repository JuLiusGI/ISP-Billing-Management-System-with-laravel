<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Proves the access matrix from MASTER_SPEC §7 holds at the route level, not
 * merely in the sidebar.
 */
class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->value('id'));

        return $user->fresh();
    }

    /** @return array<string, array{0: string}> */
    public static function everyRole(): array
    {
        return [
            'super admin' => [Role::SUPER_ADMIN],
            'administrator' => [Role::ADMINISTRATOR],
            'billing staff' => [Role::BILLING_STAFF],
            'technician' => [Role::TECHNICIAN],
            'accountant' => [Role::ACCOUNTANT],
        ];
    }

    #[DataProvider('everyRole')]
    public function test_every_role_can_reach_the_dashboard(string $role): void
    {
        $this->actingAs($this->userWithRole($role))->get(route('dashboard'))->assertOk();
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function userManagementAccess(): array
    {
        return [
            'super admin may' => [Role::SUPER_ADMIN, true],
            'administrator may' => [Role::ADMINISTRATOR, true],
            'billing staff may not' => [Role::BILLING_STAFF, false],
            'technician may not' => [Role::TECHNICIAN, false],
            'accountant may not' => [Role::ACCOUNTANT, false],
        ];
    }

    #[DataProvider('userManagementAccess')]
    public function test_user_management_is_restricted_to_administrators(string $role, bool $allowed): void
    {
        $response = $this->actingAs($this->userWithRole($role))->get(route('users.index'));

        $allowed ? $response->assertOk() : $response->assertForbidden();
    }

    #[DataProvider('userManagementAccess')]
    public function test_the_role_list_is_restricted_to_administrators(string $role, bool $allowed): void
    {
        $response = $this->actingAs($this->userWithRole($role))->get(route('roles.index'));

        $allowed ? $response->assertOk() : $response->assertForbidden();
    }

    public function test_an_administrator_can_see_roles_but_not_redefine_them(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);

        // The administrator role is granted everything except roles.manage.
        $this->actingAs($admin)->get(route('roles.index'))->assertOk();
        $this->actingAs($admin)->get(route('roles.create'))->assertForbidden();
    }

    public function test_a_super_admin_can_redefine_roles(): void
    {
        $this->actingAs($this->userWithRole(Role::SUPER_ADMIN))
            ->get(route('roles.create'))
            ->assertOk();
    }

    public function test_billing_staff_hold_billing_abilities_but_no_administration(): void
    {
        $user = $this->userWithRole(Role::BILLING_STAFF);

        $this->assertTrue($user->hasPermission('invoices.create'));
        $this->assertTrue($user->hasPermission('payments.create'));
        $this->assertTrue($user->hasPermission('customers.update'));
        $this->assertFalse($user->hasPermission('users.view'));
        $this->assertFalse($user->hasPermission('expenses.create'));
        $this->assertFalse($user->hasPermission('payments.reverse'));
    }

    public function test_technicians_hold_service_abilities_but_nothing_financial(): void
    {
        $user = $this->userWithRole(Role::TECHNICIAN);

        $this->assertTrue($user->hasPermission('subscriptions.manage_status'));
        $this->assertTrue($user->hasPermission('customers.view'));
        $this->assertFalse($user->hasPermission('invoices.create'));
        $this->assertFalse($user->hasPermission('payments.create'));
        $this->assertFalse($user->hasPermission('customers.update'));
    }

    public function test_accountants_hold_financial_abilities_but_cannot_invoice(): void
    {
        $user = $this->userWithRole(Role::ACCOUNTANT);

        $this->assertTrue($user->hasPermission('expenses.create'));
        $this->assertTrue($user->hasPermission('payments.reverse'));
        $this->assertTrue($user->hasPermission('reports.financial'));
        $this->assertFalse($user->hasPermission('invoices.create'));
        $this->assertFalse($user->hasPermission('users.view'));
    }

    public function test_a_super_admin_holds_every_ability_without_being_granted_them(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);

        $this->assertTrue($superAdmin->can('users.delete'));

        // Belt and braces: the role is granted every ability explicitly, so the
        // permission matrix shows the truth and access would survive the gate
        // short-circuit being removed...
        $granted = Role::where('name', Role::SUPER_ADMIN)->first()->permissions()->count();
        $this->assertSame(Permission::count(), $granted);

        // ...while the short-circuit additionally covers abilities that were
        // never seeded, so a new ability is never accidentally denied to them.
        $this->assertFalse($superAdmin->abilities()->contains('anything.not.even.defined'));
        $this->assertTrue($superAdmin->can('anything.not.even.defined'));
    }

    public function test_a_user_with_no_role_can_reach_nothing_beyond_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('roles.index'))->assertForbidden();
        $this->assertFalse($user->can('users.view'));
    }

    public function test_the_gate_never_lets_a_super_admin_past_the_lockout_guards(): void
    {
        // Gate::before grants dot-abilities outright, so the guards live in the
        // policy where that short-circuit cannot reach them.
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);

        $this->assertFalse($superAdmin->can('delete', $superAdmin));

        $this->actingAs($superAdmin)
            ->delete(route('users.destroy', $superAdmin))
            ->assertForbidden();

        $this->assertNotSoftDeleted($superAdmin);
    }

    public function test_revoking_an_ability_from_a_role_takes_effect_immediately(): void
    {
        $user = $this->userWithRole(Role::ADMINISTRATOR);
        $this->assertTrue($user->hasPermission('users.view'));

        $role = Role::where('name', Role::ADMINISTRATOR)->first();
        $role->permissions()->detach($role->permissions()->where('name', 'users.view')->value('permissions.id'));

        $this->assertFalse($user->forgetAbilities()->hasPermission('users.view'));
        $this->actingAs($user->fresh())->get(route('users.index'))->assertForbidden();
    }
}
