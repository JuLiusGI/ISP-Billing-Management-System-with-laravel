<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\SubscriptionService;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(ExpenseCategorySeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->value('id'));

        return $user->fresh();
    }

    private function entriesFor(string $module, ?string $action = null)
    {
        return AuditLog::where('module', $module)
            ->when($action, fn ($q) => $q->where('action', $action))
            ->get();
    }

    // -----------------------------------------------------------------
    // Model changes
    // -----------------------------------------------------------------

    public function test_creating_a_customer_is_recorded(): void
    {
        $customer = Customer::factory()->create(['first_name' => 'Recorded']);

        $entry = $this->entriesFor('Customers', 'created')->last();

        $this->assertNotNull($entry);
        $this->assertSame(Customer::class, $entry->auditable_type);
        $this->assertSame($customer->id, $entry->auditable_id);
        $this->assertStringContainsString($customer->account_number, $entry->description);
        $this->assertSame('Recorded', $entry->new_values['first_name']);
    }

    public function test_an_update_records_only_what_changed(): void
    {
        $customer = Customer::factory()->create(['first_name' => 'Before', 'contact_number' => '09170000000']);

        $customer->update(['first_name' => 'After']);

        $entry = $this->entriesFor('Customers', 'updated')->last();

        $this->assertSame(['first_name' => 'After'], $entry->new_values);
        $this->assertSame(['first_name' => 'Before'], $entry->old_values);
        // The untouched field is absent rather than repeated on both sides.
        $this->assertArrayNotHasKey('contact_number', $entry->new_values);
    }

    public function test_saving_without_changing_anything_records_nothing(): void
    {
        $customer = Customer::factory()->create();
        $before = AuditLog::count();

        $customer->update(['first_name' => $customer->first_name]);

        $this->assertSame($before, AuditLog::count());
    }

    public function test_archiving_and_restoring_are_distinguished_from_deletion(): void
    {
        $customer = Customer::factory()->create();

        $customer->delete();
        $this->assertNotNull($this->entriesFor('Customers', 'archived')->last());

        $customer->restore();
        $this->assertNotNull($this->entriesFor('Customers', 'restored')->last());

        $customer->forceDelete();
        $this->assertNotNull($this->entriesFor('Customers', 'deleted')->last());
    }

    public function test_each_audited_module_writes_under_its_own_name(): void
    {
        Customer::factory()->create();
        Subscription::factory()->create();
        Expense::factory()->create(['expense_category_id' => ExpenseCategory::first()->id]);
        User::factory()->create();

        foreach (['Customers', 'Subscriptions', 'Expenses', 'Administration', 'Internet Plans'] as $module) {
            $this->assertTrue(
                $this->entriesFor($module)->isNotEmpty(),
                "Expected at least one audit entry for the {$module} module."
            );
        }
    }

    public function test_a_password_is_never_written_to_the_trail(): void
    {
        $user = User::factory()->create(['password' => 'a-real-secret-value']);

        $entry = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('action', 'created')
            ->first();

        $this->assertSame('[redacted]', $entry->new_values['password']);
        $this->assertStringNotContainsString('a-real-secret-value', json_encode($entry->new_values));
        $this->assertSame('[redacted]', $entry->new_values['remember_token'] ?? '[redacted]');
    }

    public function test_sign_in_bookkeeping_does_not_create_account_change_entries(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $before = AuditLog::where('action', 'updated')->count();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        // last_login_at moved, but that is the login event's business.
        $this->assertSame($before, AuditLog::where('action', 'updated')->count());
    }

    // -----------------------------------------------------------------
    // Authentication
    // -----------------------------------------------------------------

    public function test_a_successful_sign_in_is_recorded_with_its_origin(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $entry = AuditLog::where('action', 'login')->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame('Authentication', $entry->module);
        $this->assertNotNull($entry->ip_address);
        $this->assertNotNull($entry->user_agent);
    }

    public function test_signing_out_is_recorded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'));

        $this->assertSame(1, AuditLog::where('action', 'logout')->where('user_id', $user->id)->count());
    }

    public function test_a_failed_sign_in_is_recorded_without_the_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'the-wrong-one']);

        $entry = AuditLog::where('action', 'login_failed')->latest('id')->first();

        $this->assertNotNull($entry, 'A failed attempt is the one an audit trail most needs.');
        $this->assertStringContainsString($user->email, $entry->description);
        $this->assertStringNotContainsString('the-wrong-one', json_encode($entry->toArray()));
    }

    public function test_throttling_is_recorded(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        foreach (range(1, 6) as $ignored) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->assertGreaterThanOrEqual(
            1,
            AuditLog::where('action', 'login_throttled')->count()
        );
    }

    // -----------------------------------------------------------------
    // Service status and settings
    // -----------------------------------------------------------------

    public function test_a_service_status_change_reaches_the_trail(): void
    {
        $actor = $this->userWithRole(Role::TECHNICIAN);
        $subscription = Subscription::factory()->create(['status' => SubscriptionStatus::Active]);

        app(SubscriptionService::class)->changeStatus(
            $subscription, SubscriptionStatus::Suspended, 'Non-payment', $actor
        );

        $entry = AuditLog::where('action', 'service_status_changed')->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertSame($actor->id, $entry->user_id);
        $this->assertSame('active', $entry->old_values['status']);
        $this->assertSame('suspended', $entry->new_values['status']);
        $this->assertStringContainsString('Non-payment', $entry->description);
    }

    public function test_a_settings_change_is_recorded(): void
    {
        $this->seed(SystemSettingSeeder::class);

        app(SettingsService::class)->set('billing.invoice_prefix', 'ACME');

        $entry = AuditLog::where('module', 'Settings')->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertSame('ACME', $entry->new_values['value']);
        $this->assertSame('INV', $entry->old_values['value']);
    }

    // -----------------------------------------------------------------
    // The trail rolls back with what it describes
    // -----------------------------------------------------------------

    public function test_an_audit_entry_does_not_survive_a_rolled_back_change(): void
    {
        $before = AuditLog::count();

        try {
            \DB::transaction(function (): void {
                Customer::factory()->create();

                throw new \RuntimeException('something went wrong afterwards');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, Customer::count());
        $this->assertSame($before, AuditLog::count());
    }

    // -----------------------------------------------------------------
    // The viewer
    // -----------------------------------------------------------------

    public function test_the_viewer_lists_and_filters_entries(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);
        Customer::factory()->count(3)->create();
        Expense::factory()->create(['expense_category_id' => ExpenseCategory::first()->id]);

        $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk();

        $this->actingAs($admin)
            ->get(route('audit-logs.index', ['module' => 'Customers', 'action' => 'created']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 3);
    }

    public function test_the_viewer_can_filter_by_user_and_date(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);

        $this->actingAs($admin)->get(route('audit-logs.index', [
            'user' => $admin->id,
            'from' => now()->subDay()->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk();
    }

    public function test_the_detail_view_pairs_before_and_after(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);
        $customer = Customer::factory()->create(['first_name' => 'Before']);
        $customer->update(['first_name' => 'After']);

        $entry = AuditLog::where('action', 'updated')->latest('id')->first();

        $this->actingAs($admin)
            ->get(route('audit-logs.show', $entry))
            ->assertOk()
            ->assertSee('Before')
            ->assertSee('After')
            ->assertViewHas('changes', fn (array $changes) => $changes[0]['field'] === 'first_name'
                && $changes[0]['old'] === 'Before'
                && $changes[0]['new'] === 'After');
    }

    public function test_the_trail_is_read_only(): void
    {
        // There is deliberately no route to create, edit or delete an entry.
        foreach (['audit-logs.store', 'audit-logs.update', 'audit-logs.destroy'] as $name) {
            $this->assertNull(
                app('router')->getRoutes()->getByName($name),
                "A {$name} route would make the trail editable from the interface it audits."
            );
        }
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_only_administrators_reach_the_trail(): void
    {
        foreach ([Role::SUPER_ADMIN, Role::ADMINISTRATOR] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('audit-logs.index'))->assertOk();
        }

        foreach ([Role::BILLING_STAFF, Role::TECHNICIAN, Role::ACCOUNTANT] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('audit-logs.index'))->assertForbidden();
        }
    }

    public function test_a_user_with_no_role_cannot_reach_the_trail(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('audit-logs.index'))
            ->assertForbidden();
    }
}
