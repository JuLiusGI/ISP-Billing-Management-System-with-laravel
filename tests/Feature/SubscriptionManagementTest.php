<?php

namespace Tests\Feature;

use App\Enums\CustomerConnectionStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\Role;
use App\Models\ServiceStatusLog;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionManagementTest extends TestCase
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
    private function validPayload(Customer $customer, InternetPlan $plan, array $overrides = []): array
    {
        return array_replace([
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'start_date' => '2026-08-01',
            'activation_date' => null,
            'expiration_date' => null,
            'billing_day' => 15,
            'monthly_rate' => '1499.00',
            'installation_fee' => '1500.00',
            'discount_amount' => '0.00',
            'connection_type' => 'fiber',
            'static_ip' => null,
            'username' => 'line-0001@isp',
            'service_notes' => null,
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Listing and forms
    // -----------------------------------------------------------------

    public function test_the_list_and_forms_render(): void
    {
        $staff = $this->userWithRole(Role::BILLING_STAFF);
        $subscription = Subscription::factory()->create();

        $this->actingAs($staff)->get(route('subscriptions.index'))->assertOk();
        $this->actingAs($staff)->get(route('subscriptions.create'))->assertOk();
        $this->actingAs($staff)->get(route('subscriptions.show', $subscription))->assertOk();
        $this->actingAs($staff)->get(route('subscriptions.edit', $subscription))->assertOk();
    }

    public function test_the_create_form_can_arrive_with_a_customer_preselected(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->get(route('subscriptions.create', ['customer' => $customer->id]))
            ->assertOk()
            ->assertViewHas('selectedCustomer', fn ($c) => $c?->is($customer));
    }

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        $plan = InternetPlan::factory()->create();
        $target = Customer::factory()->create(['last_name' => 'Villanueva']);
        Subscription::factory()->for($target)->forPlan($plan)->create();
        Subscription::factory()->count(3)->suspended()->create();

        $staff = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($staff)->get(route('subscriptions.index', ['search' => 'Villanueva']))
            ->assertViewHas('subscriptions', fn ($s) => $s->total() === 1);

        $this->actingAs($staff)->get(route('subscriptions.index', ['status' => 'suspended']))
            ->assertViewHas('subscriptions', fn ($s) => $s->total() === 3);

        $this->actingAs($staff)->get(route('subscriptions.index', ['plan' => $plan->id]))
            ->assertViewHas('subscriptions', fn ($s) => $s->total() === 1);
    }

    // -----------------------------------------------------------------
    // Creating
    // -----------------------------------------------------------------

    public function test_a_subscription_is_created_pending_with_a_generated_code(): void
    {
        $customer = Customer::factory()->create();
        $plan = InternetPlan::factory()->priced(1499)->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('subscriptions.store'), $this->validPayload($customer, $plan))
            ->assertRedirect();

        $subscription = Subscription::first();

        $this->assertMatchesRegularExpression('/^SUB-\d{4}-\d{5}$/', $subscription->subscription_code);
        $this->assertSame(SubscriptionStatus::Pending, $subscription->status);
        $this->assertNull($subscription->activation_date);
    }

    public function test_creating_a_subscription_records_its_first_status_log(): void
    {
        $customer = Customer::factory()->create();
        $plan = InternetPlan::factory()->create();
        $actor = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($actor)->post(route('subscriptions.store'), $this->validPayload($customer, $plan));

        $log = ServiceStatusLog::first();

        $this->assertNotNull($log);
        $this->assertNull($log->from_status);
        $this->assertSame('pending', $log->to_status);
        $this->assertSame($actor->id, $log->changed_by);
    }

    public function test_the_agreed_rate_is_stored_on_the_subscription_not_read_from_the_plan(): void
    {
        $customer = Customer::factory()->create();
        $plan = InternetPlan::factory()->priced(1499)->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->post(
            route('subscriptions.store'),
            // A negotiated rate below list price.
            $this->validPayload($customer, $plan, ['monthly_rate' => '1200.00'])
        );

        $subscription = Subscription::first();
        $this->assertSame('1200.00', $subscription->monthly_rate);

        $plan->update(['monthly_price' => 5000]);
        $this->assertSame('1200.00', $subscription->refresh()->monthly_rate);
    }

    public function test_the_net_monthly_rate_subtracts_the_discount(): void
    {
        $customer = Customer::factory()->create();
        $plan = InternetPlan::factory()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->post(
            route('subscriptions.store'),
            $this->validPayload($customer, $plan, ['monthly_rate' => '1499.00', 'discount_amount' => '299.00'])
        );

        $this->assertSame('1200.00', Subscription::first()->net_monthly_rate);
    }

    // -----------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------

    public function test_a_discount_larger_than_the_rate_is_rejected(): void
    {
        $customer = Customer::factory()->create();
        $plan = InternetPlan::factory()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->post(
            route('subscriptions.store'),
            $this->validPayload($customer, $plan, ['monthly_rate' => '999.00', 'discount_amount' => '1500.00'])
        )->assertSessionHasErrors('discount_amount');

        $this->assertSame(0, Subscription::count());
    }

    public function test_invalid_service_details_are_rejected(): void
    {
        $customer = Customer::factory()->create();
        $plan = InternetPlan::factory()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->post(
            route('subscriptions.store'),
            $this->validPayload($customer, $plan, [
                'billing_day' => 45,
                'static_ip' => 'not-an-ip',
                'expiration_date' => '2020-01-01',
            ])
        )->assertSessionHasErrors(['billing_day', 'static_ip', 'expiration_date']);
    }

    public function test_a_duplicate_service_username_is_rejected(): void
    {
        Subscription::factory()->create(['username' => 'taken@isp']);
        $customer = Customer::factory()->create();
        $plan = InternetPlan::factory()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->post(
            route('subscriptions.store'),
            $this->validPayload($customer, $plan, ['username' => 'taken@isp'])
        )->assertSessionHasErrors('username');
    }

    // -----------------------------------------------------------------
    // Status transitions
    // -----------------------------------------------------------------

    public function test_activating_a_pending_subscription_stamps_the_activation_date(): void
    {
        $subscription = Subscription::factory()->pending()->create();

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))->patch(
            route('subscriptions.status', $subscription),
            ['status' => 'active', 'reason' => 'Installation completed']
        )->assertRedirect();

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNotNull($subscription->activation_date);
    }

    public function test_reactivating_does_not_overwrite_the_original_activation_date(): void
    {
        $subscription = Subscription::factory()->suspended()->create([
            'activation_date' => '2026-01-15',
        ]);

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))->patch(
            route('subscriptions.status', $subscription),
            ['status' => 'active']
        );

        $this->assertSame('2026-01-15', $subscription->refresh()->activation_date->format('Y-m-d'));
    }

    public function test_every_status_change_is_logged_with_its_reason(): void
    {
        $subscription = Subscription::factory()->create();
        $technician = $this->userWithRole(Role::TECHNICIAN);

        $this->actingAs($technician)->patch(
            route('subscriptions.status', $subscription),
            ['status' => 'suspended', 'reason' => 'Non-payment']
        );

        $log = ServiceStatusLog::latest('id')->first();

        $this->assertSame('active', $log->from_status);
        $this->assertSame('suspended', $log->to_status);
        $this->assertSame('Non-payment', $log->reason);
        $this->assertSame($technician->id, $log->changed_by);
        $this->assertFalse($log->is_automatic);
    }

    public function test_an_illegal_transition_is_refused(): void
    {
        // Pending lines are switched on or abandoned; they cannot be suspended.
        $subscription = Subscription::factory()->pending()->create();

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))->patch(
            route('subscriptions.status', $subscription),
            ['status' => 'suspended']
        )->assertSessionHas('error');

        $this->assertSame(SubscriptionStatus::Pending, $subscription->refresh()->status);
        $this->assertSame(0, ServiceStatusLog::count());
    }

    public function test_moving_to_the_status_it_already_holds_is_refused(): void
    {
        $subscription = Subscription::factory()->create();

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))->patch(
            route('subscriptions.status', $subscription),
            ['status' => 'active']
        )->assertSessionHas('error');

        $this->assertSame(0, ServiceStatusLog::count());
    }

    public function test_a_cancelled_subscription_is_terminal(): void
    {
        $subscription = Subscription::factory()->cancelled()->create();
        $admin = $this->userWithRole(Role::ADMINISTRATOR);

        $this->actingAs($admin)
            ->patch(route('subscriptions.status', $subscription), ['status' => 'active'])
            ->assertForbidden();

        $this->actingAs($admin)->get(route('subscriptions.edit', $subscription))->assertForbidden();
    }

    public function test_activation_and_suspension_move_the_customer_connection_status(): void
    {
        $customer = Customer::factory()->create([
            'connection_status' => CustomerConnectionStatus::Pending,
        ]);
        $subscription = Subscription::factory()->for($customer)->pending()->create();
        $technician = $this->userWithRole(Role::TECHNICIAN);

        $this->actingAs($technician)->patch(
            route('subscriptions.status', $subscription),
            ['status' => 'active']
        );
        $this->assertSame(CustomerConnectionStatus::Connected, $customer->refresh()->connection_status);

        $this->actingAs($technician)->patch(
            route('subscriptions.status', $subscription),
            ['status' => 'suspended']
        );
        $this->assertSame(CustomerConnectionStatus::Disconnected, $customer->refresh()->connection_status);
    }

    public function test_a_customer_stays_connected_while_any_line_is_active(): void
    {
        $customer = Customer::factory()->create();
        $first = Subscription::factory()->for($customer)->create();
        Subscription::factory()->for($customer)->create();

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))->patch(
            route('subscriptions.status', $first),
            ['status' => 'suspended']
        );

        // The second line is still up.
        $this->assertSame(CustomerConnectionStatus::Connected, $customer->refresh()->connection_status);
    }

    // -----------------------------------------------------------------
    // Editing
    // -----------------------------------------------------------------

    public function test_editing_cannot_change_the_status_or_the_customer(): void
    {
        $subscription = Subscription::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->put(
            route('subscriptions.update', $subscription),
            $this->validPayload($otherCustomer, $subscription->internetPlan, [
                'status' => 'cancelled',
                'username' => 'moved@isp',
            ])
        )->assertRedirect();

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNotSame($otherCustomer->id, $subscription->customer_id);
        $this->assertSame('moved@isp', $subscription->username);
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_billing_staff_can_create_and_edit_but_not_change_status(): void
    {
        $staff = $this->userWithRole(Role::BILLING_STAFF);
        $subscription = Subscription::factory()->create();

        $this->actingAs($staff)->get(route('subscriptions.create'))->assertOk();
        $this->actingAs($staff)->get(route('subscriptions.edit', $subscription))->assertOk();
        $this->actingAs($staff)
            ->patch(route('subscriptions.status', $subscription), ['status' => 'suspended'])
            ->assertForbidden();
    }

    public function test_a_technician_can_change_status_but_not_create(): void
    {
        $technician = $this->userWithRole(Role::TECHNICIAN);
        $subscription = Subscription::factory()->create();

        $this->actingAs($technician)->get(route('subscriptions.create'))->assertForbidden();
        $this->actingAs($technician)
            ->patch(route('subscriptions.status', $subscription), ['status' => 'suspended'])
            ->assertRedirect();

        $this->assertSame(SubscriptionStatus::Suspended, $subscription->refresh()->status);
    }

    public function test_an_accountant_cannot_reach_subscriptions(): void
    {
        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->get(route('subscriptions.index'))
            ->assertForbidden();
    }
}
