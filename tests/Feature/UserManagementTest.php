<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_an_administrator_can_list_users(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);
        User::factory()->count(3)->create();

        $this->actingAs($admin)->get(route('users.index'))->assertOk()->assertSee('Staff accounts');
    }

    public function test_a_technician_cannot_reach_user_management(): void
    {
        $technician = $this->userWithRole(Role::TECHNICIAN);

        // The sidebar hides the link, but the route must refuse it too.
        $this->actingAs($technician)->get(route('users.index'))->assertForbidden();
        $this->actingAs($technician)->get(route('users.create'))->assertForbidden();
    }

    public function test_a_user_with_no_roles_cannot_reach_user_management(): void
    {
        $this->actingAs(User::factory()->create())->get(route('users.index'))->assertForbidden();
    }

    public function test_an_administrator_can_create_a_user_with_roles(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);
        $billingRoleId = Role::where('name', Role::BILLING_STAFF)->value('id');

        $this->actingAs($admin)->post(route('users.store'), [
            'first_name' => 'New',
            'last_name' => 'Staffer',
            'email' => 'new.staffer@example.com',
            'phone' => '09170000000',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
            'status' => UserStatus::Active->value,
            'roles' => [$billingRoleId],
        ])->assertRedirect();

        $created = User::where('email', 'new.staffer@example.com')->first();

        $this->assertNotNull($created);
        $this->assertTrue(Hash::check('a-strong-password', $created->password));
        $this->assertTrue($created->hasRole(Role::BILLING_STAFF));
    }

    public function test_creating_a_user_requires_at_least_one_role(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);

        $this->actingAs($admin)->post(route('users.store'), [
            'first_name' => 'No',
            'last_name' => 'Role',
            'email' => 'no.role@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
            'status' => UserStatus::Active->value,
        ])->assertSessionHasErrors('roles');

        $this->assertDatabaseMissing('users', ['email' => 'no.role@example.com']);
    }

    public function test_a_blank_password_on_edit_leaves_the_existing_one_alone(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);
        $target = User::factory()->create(['password' => 'original-password']);
        $target->roles()->attach(Role::where('name', Role::TECHNICIAN)->value('id'));

        $this->actingAs($admin)->put(route('users.update', $target), [
            'first_name' => 'Edited',
            'last_name' => $target->last_name,
            'email' => $target->email,
            'password' => '',
            'password_confirmation' => '',
            'status' => UserStatus::Active->value,
            'roles' => [Role::where('name', Role::TECHNICIAN)->value('id')],
        ])->assertRedirect();

        $target->refresh();
        $this->assertSame('Edited', $target->first_name);
        $this->assertTrue(Hash::check('original-password', $target->password));
    }

    public function test_an_administrator_cannot_suspend_their_own_account(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'first_name' => $admin->first_name,
            'last_name' => $admin->last_name,
            'email' => $admin->email,
            'status' => UserStatus::Suspended->value,
            'roles' => [Role::where('name', Role::ADMINISTRATOR)->value('id')],
        ])->assertForbidden();

        $this->assertSame(UserStatus::Active, $admin->refresh()->status);
    }

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);

        $this->actingAs($admin)->delete(route('users.destroy', $admin));

        $this->assertNotSoftDeleted($admin);
    }

    public function test_the_last_super_admin_cannot_be_deleted(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $other = $this->userWithRole(Role::ADMINISTRATOR);

        $this->actingAs($other)->delete(route('users.destroy', $superAdmin));

        $this->assertNotSoftDeleted($superAdmin);
    }

    public function test_deleting_a_user_is_a_soft_delete(): void
    {
        $admin = $this->userWithRole(Role::SUPER_ADMIN);
        $target = User::factory()->create();

        $this->actingAs($admin)->delete(route('users.destroy', $target))->assertRedirect(route('users.index'));

        $this->assertSoftDeleted($target);
    }

    public function test_the_user_list_can_be_searched_and_filtered(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);
        User::factory()->create(['last_name' => 'Villanueva']);
        User::factory()->count(3)->create(['last_name' => 'Reyes']);

        $this->actingAs($admin)
            ->get(route('users.index', ['search' => 'Villanueva']))
            ->assertOk()
            ->assertSee('Villanueva')
            ->assertDontSee('Reyes');
    }
}
