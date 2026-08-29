<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
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

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'first_name' => 'Maria',
            'middle_name' => 'Reyes',
            'last_name' => 'Santos',
            'suffix' => null,
            'gender' => 'female',
            'date_of_birth' => '1990-04-12',
            'contact_number' => '09171234567',
            'alternate_contact_number' => null,
            'email' => 'maria.santos@example.com',
            'customer_type' => 'residential',
            'installation_date' => '2026-08-01',
            'status' => CustomerStatus::Active->value,
            'account_status' => 'good_standing',
            'connection_status' => 'connected',
            'notes' => 'Prefers afternoon visits.',
            'address' => [
                'house_building' => '12',
                'street' => 'Mabini Street',
                'barangay' => 'Barangay 5',
                'municipality_city' => 'Catbalogan',
                'province' => 'Samar',
                'postal_code' => '6700',
            ],
            'contacts' => [
                ['name' => 'Jose Santos', 'relationship' => 'Spouse', 'contact_number' => '09189998888', 'email' => null],
            ],
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Listing, search and filtering
    // -----------------------------------------------------------------

    public function test_the_customer_list_renders(): void
    {
        Customer::factory()->count(3)->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->get(route('customers.index'))
            ->assertOk();
    }

    public function test_the_list_can_be_searched_by_account_number_and_name(): void
    {
        $target = Customer::factory()->create(['last_name' => 'Villanueva']);
        Customer::factory()->count(4)->create(['last_name' => 'Reyes']);

        $staff = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($staff)->get(route('customers.index', ['search' => 'Villanueva']))
            ->assertOk()->assertSee('Villanueva')->assertDontSee('Reyes');

        $this->actingAs($staff)->get(route('customers.index', ['search' => $target->account_number]))
            ->assertOk()->assertSee($target->account_number);
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        Customer::factory()->count(2)->create(['status' => CustomerStatus::Active]);
        Customer::factory()->count(3)->suspended()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->get(route('customers.index', ['status' => 'suspended']))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 3);
    }

    public function test_archived_customers_are_hidden_unless_asked_for(): void
    {
        $archived = Customer::factory()->create();
        $archived->delete();
        Customer::factory()->count(2)->create();

        $staff = $this->userWithRole(Role::ADMINISTRATOR);

        $this->actingAs($staff)->get(route('customers.index'))
            ->assertViewHas('customers', fn ($c) => $c->total() === 2);

        $this->actingAs($staff)->get(route('customers.index', ['archived' => 1]))
            ->assertViewHas('customers', fn ($c) => $c->total() === 1);
    }

    // -----------------------------------------------------------------
    // Creating
    // -----------------------------------------------------------------

    public function test_the_create_form_renders(): void
    {
        // The create form has no $customer to read from, which is a different
        // code path in the shared partial than the edit form.
        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->get(route('customers.create'))
            ->assertOk()
            ->assertSee('New customer');
    }

    public function test_the_edit_form_renders_with_existing_values(): void
    {
        $customer = Customer::factory()->create(['first_name' => 'Existing']);
        $customer->addresses()->create([
            'type' => 'service', 'barangay' => 'Barangay 7', 'municipality_city' => 'Calbayog',
            'province' => 'Samar', 'is_primary' => true,
        ]);

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->get(route('customers.edit', $customer))
            ->assertOk()
            ->assertSee('Existing')
            ->assertSee('Calbayog');
    }

    public function test_a_customer_is_created_with_address_and_contacts(): void
    {
        $staff = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($staff)->post(route('customers.store'), $this->validPayload())
            ->assertRedirect();

        $customer = Customer::where('email', 'maria.santos@example.com')->first();

        $this->assertNotNull($customer);
        $this->assertMatchesRegularExpression('/^ACC-\d{4}-\d{5}$/', $customer->account_number);
        $this->assertSame($staff->id, $customer->created_by);
        $this->assertSame('Catbalogan', $customer->primaryAddress->municipality_city);
        $this->assertTrue($customer->primaryAddress->is_primary);
        $this->assertCount(1, $customer->contacts);
        $this->assertSame('Jose Santos', $customer->contacts->first()->name);
    }

    public function test_account_numbers_are_generated_and_never_taken_from_input(): void
    {
        $staff = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($staff)->post(
            route('customers.store'),
            $this->validPayload(['account_number' => 'ATTACKER-SUPPLIED'])
        );

        $this->assertDatabaseMissing('customers', ['account_number' => 'ATTACKER-SUPPLIED']);
        $this->assertSame(1, Customer::count());
    }

    public function test_blank_contact_rows_are_discarded(): void
    {
        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->post(
            route('customers.store'),
            $this->validPayload(['contacts' => [
                ['name' => 'Real Person', 'relationship' => null, 'contact_number' => '09170000000', 'email' => null],
                ['name' => null, 'relationship' => null, 'contact_number' => null, 'email' => null],
            ]])
        )->assertRedirect();

        $this->assertSame(1, Customer::first()->contacts()->count());
    }

    public function test_a_half_filled_contact_row_is_rejected(): void
    {
        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->post(
            route('customers.store'),
            $this->validPayload(['contacts' => [
                ['name' => 'Missing Number', 'relationship' => null, 'contact_number' => null, 'email' => null],
            ]])
        )->assertSessionHasErrors('contacts.0.contact_number');

        $this->assertSame(0, Customer::count());
    }

    public function test_required_fields_are_enforced(): void
    {
        $payload = $this->validPayload();
        unset($payload['first_name'], $payload['last_name'], $payload['contact_number']);
        $payload['address']['barangay'] = '';

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('customers.store'), $payload)
            ->assertSessionHasErrors(['first_name', 'last_name', 'contact_number', 'address.barangay']);
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        Customer::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('customers.store'), $this->validPayload(['email' => 'taken@example.com']))
            ->assertSessionHasErrors('email');
    }

    public function test_an_archived_customer_does_not_block_their_email(): void
    {
        $archived = Customer::factory()->create(['email' => 'released@example.com']);
        $archived->delete();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('customers.store'), $this->validPayload(['email' => 'released@example.com']))
            ->assertSessionHasNoErrors();
    }

    public function test_a_profile_photo_is_stored(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->post(
            route('customers.store'),
            $this->validPayload() + ['photo' => UploadedFile::fake()->image('customer.jpg')]
        )->assertRedirect();

        $customer = Customer::first();

        $this->assertNotNull($customer->photo_path);
        Storage::disk('public')->assertExists($customer->photo_path);
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->post(
            route('customers.store'),
            $this->validPayload() + ['photo' => UploadedFile::fake()->create('payload.php', 10)]
        )->assertSessionHasErrors('photo');
    }

    // -----------------------------------------------------------------
    // Updating
    // -----------------------------------------------------------------

    public function test_editing_updates_the_address_in_place_rather_than_adding_one(): void
    {
        $customer = Customer::factory()->create();
        $customer->addresses()->create([
            'type' => 'service', 'barangay' => 'Old', 'municipality_city' => 'Old City',
            'province' => 'Old Province', 'is_primary' => true,
        ]);

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->put(
            route('customers.update', $customer),
            $this->validPayload(['email' => null])
        )->assertRedirect();

        $this->assertSame(1, $customer->addresses()->count());
        $this->assertSame('Catbalogan', $customer->fresh()->primaryAddress->municipality_city);
    }

    public function test_editing_replaces_the_contact_list(): void
    {
        $customer = Customer::factory()->create();
        $customer->contacts()->create(['name' => 'Old Contact', 'contact_number' => '09170000000']);

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->put(
            route('customers.update', $customer),
            $this->validPayload(['email' => null])
        );

        $contacts = $customer->fresh()->contacts;

        $this->assertCount(1, $contacts);
        $this->assertSame('Jose Santos', $contacts->first()->name);
    }

    public function test_a_customer_keeps_their_account_number_across_an_edit(): void
    {
        $customer = Customer::factory()->create();
        $original = $customer->account_number;

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->put(
            route('customers.update', $customer),
            $this->validPayload(['email' => null, 'account_number' => 'CHANGED'])
        );

        $this->assertSame($original, $customer->fresh()->account_number);
    }

    // -----------------------------------------------------------------
    // Archiving
    // -----------------------------------------------------------------

    public function test_a_customer_can_be_archived_and_restored(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));
        $this->assertSoftDeleted($customer);

        $this->actingAs($admin)->post(route('customers.restore', $customer->id))->assertRedirect();
        $this->assertNotSoftDeleted($customer);
    }

    public function test_a_customer_who_still_owes_money_cannot_be_archived(): void
    {
        $customer = Customer::factory()->create();
        Invoice::factory()->for($customer)->create([
            'total_amount' => 1500, 'balance_due' => 1500, 'status' => 'unpaid',
        ]);

        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->delete(route('customers.destroy', $customer))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($customer);
    }

    public function test_archiving_keeps_the_customers_invoices(): void
    {
        $customer = Customer::factory()->create();
        Invoice::factory()->for($customer)->create([
            'total_amount' => 500, 'balance_due' => 0, 'status' => 'paid',
        ]);

        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->delete(route('customers.destroy', $customer));

        $this->assertSoftDeleted($customer);
        $this->assertSame(1, Invoice::where('customer_id', $customer->id)->count());
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_a_technician_may_look_but_not_change(): void
    {
        $technician = $this->userWithRole(Role::TECHNICIAN);
        $customer = Customer::factory()->create();

        $this->actingAs($technician)->get(route('customers.index'))->assertOk();
        $this->actingAs($technician)->get(route('customers.show', $customer))->assertOk();
        $this->actingAs($technician)->get(route('customers.create'))->assertForbidden();
        $this->actingAs($technician)->get(route('customers.edit', $customer))->assertForbidden();
        $this->actingAs($technician)->delete(route('customers.destroy', $customer))->assertForbidden();
    }

    public function test_billing_staff_can_edit_but_not_archive(): void
    {
        $staff = $this->userWithRole(Role::BILLING_STAFF);
        $customer = Customer::factory()->create();

        $this->actingAs($staff)->get(route('customers.edit', $customer))->assertOk();
        $this->actingAs($staff)->delete(route('customers.destroy', $customer))->assertForbidden();
        $this->assertNotSoftDeleted($customer);
    }

    public function test_a_user_with_no_role_reaches_nothing(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('customers.index'))
            ->assertForbidden();
    }

    public function test_the_customer_profile_renders_with_its_billing_summary(): void
    {
        $customer = Customer::factory()->create();
        Invoice::factory()->for($customer)->create([
            'total_amount' => 1500, 'balance_due' => 1500, 'status' => 'unpaid',
        ]);

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee($customer->account_number)
            ->assertViewHas('outstandingBalance', '1500.00');
    }
}
