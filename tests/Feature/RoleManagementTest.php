<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', Role::SUPER_ADMIN)->value('id'));

        return $user->fresh();
    }

    public function test_a_role_can_be_created_with_abilities(): void
    {
        $abilities = Permission::whereIn('name', ['customers.view', 'invoices.view'])->pluck('id')->all();

        $this->actingAs($this->superAdmin())->post(route('roles.store'), [
            'display_name' => 'Support Desk',
            'description' => 'Read-only access for the support team.',
            'permissions' => $abilities,
        ])->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'support-desk')->first();

        $this->assertNotNull($role, 'The machine name should be slugged from the display name.');
        $this->assertFalse($role->is_system);
        $this->assertSame(2, $role->permissions()->count());
    }

    public function test_a_role_must_be_given_at_least_one_ability(): void
    {
        $this->actingAs($this->superAdmin())->post(route('roles.store'), [
            'display_name' => 'Empty Role',
            'permissions' => [],
        ])->assertSessionHasErrors('permissions');

        $this->assertDatabaseMissing('roles', ['name' => 'empty-role']);
    }

    public function test_duplicate_role_names_are_rejected(): void
    {
        $this->actingAs($this->superAdmin())->post(route('roles.store'), [
            'display_name' => 'Technician',
            'permissions' => Permission::limit(1)->pluck('id')->all(),
        ])->assertSessionHasErrors('name');
    }

    public function test_editing_a_role_replaces_its_abilities(): void
    {
        $role = Role::where('name', Role::TECHNICIAN)->first();
        $replacement = Permission::whereIn('name', ['customers.view'])->pluck('id')->all();

        $this->actingAs($this->superAdmin())->put(route('roles.update', $role), [
            'display_name' => 'Field Technician',
            'description' => 'Updated description.',
            'permissions' => $replacement,
        ])->assertRedirect(route('roles.index'));

        $role->refresh();

        $this->assertSame('Field Technician', $role->display_name);
        $this->assertSame(['customers.view'], $role->permissions->pluck('name')->all());
        // The identifier is what code and seeders refer to, so it stays put.
        $this->assertSame(Role::TECHNICIAN, $role->name);
    }

    public function test_the_super_admin_role_cannot_be_edited(): void
    {
        $role = Role::where('name', Role::SUPER_ADMIN)->first();

        $this->actingAs($this->superAdmin())->get(route('roles.edit', $role))->assertForbidden();

        $this->actingAs($this->superAdmin())->put(route('roles.update', $role), [
            'display_name' => 'Hijacked',
            'permissions' => Permission::limit(1)->pluck('id')->all(),
        ])->assertForbidden();
    }

    public function test_a_system_role_cannot_be_deleted(): void
    {
        $role = Role::where('name', Role::ACCOUNTANT)->first();

        $this->actingAs($this->superAdmin())->delete(route('roles.destroy', $role))->assertForbidden();

        $this->assertDatabaseHas('roles', ['name' => Role::ACCOUNTANT]);
    }

    public function test_a_role_still_assigned_to_someone_cannot_be_deleted(): void
    {
        $role = Role::create([
            'name' => 'temp-role',
            'display_name' => 'Temp Role',
            'is_system' => false,
        ]);

        User::factory()->create()->roles()->attach($role->id);

        $this->actingAs($this->superAdmin())->delete(route('roles.destroy', $role))->assertForbidden();

        $this->assertDatabaseHas('roles', ['name' => 'temp-role']);
    }

    public function test_an_unused_custom_role_can_be_deleted(): void
    {
        $role = Role::create([
            'name' => 'disposable',
            'display_name' => 'Disposable',
            'is_system' => false,
        ]);

        $this->actingAs($this->superAdmin())
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseMissing('roles', ['name' => 'disposable']);
    }

    public function test_deleting_a_role_removes_its_grants(): void
    {
        $role = Role::create(['name' => 'grants-test', 'display_name' => 'Grants Test', 'is_system' => false]);
        $role->permissions()->sync(Permission::limit(3)->pluck('id')->all());

        $this->assertDatabaseCount('permission_role', Role::has('permissions')->get()
            ->sum(fn (Role $r) => $r->permissions()->count()));

        $this->actingAs($this->superAdmin())->delete(route('roles.destroy', $role));

        $this->assertDatabaseMissing('permission_role', ['role_id' => $role->id]);
    }

    public function test_a_role_without_the_manage_ability_cannot_reach_the_form(): void
    {
        $billing = User::factory()->create();
        $billing->roles()->attach(Role::where('name', Role::BILLING_STAFF)->value('id'));

        $this->actingAs($billing->fresh())->get(route('roles.create'))->assertForbidden();
        $this->actingAs($billing->fresh())->post(route('roles.store'), [
            'display_name' => 'Sneaky',
            'permissions' => Permission::limit(1)->pluck('id')->all(),
        ])->assertForbidden();
    }
}
