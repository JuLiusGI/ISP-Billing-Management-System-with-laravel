<?php

namespace Tests\Feature;

use App\Contracts\ServiceProvisioner;
use App\Enums\CustomerConnectionStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Role;
use App\Models\ServiceStatusLog;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Provisioning\NullServiceProvisioner;
use App\Services\SubscriptionService;
use Database\Seeders\RoleAndPermissionSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
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

    // -----------------------------------------------------------------
    // The service board
    // -----------------------------------------------------------------

    public function test_the_board_defaults_to_active_services(): void
    {
        Subscription::factory()->count(3)->create(['status' => SubscriptionStatus::Active]);
        Subscription::factory()->count(2)->suspended()->create();

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('services.index'))
            ->assertOk()
            ->assertViewHas('status', SubscriptionStatus::Active)
            ->assertViewHas('services', fn ($services) => $services->total() === 3);
    }

    public function test_the_board_can_show_each_service_state(): void
    {
        Subscription::factory()->count(2)->create(['status' => SubscriptionStatus::Active]);
        Subscription::factory()->count(3)->suspended()->create();
        Subscription::factory()->count(1)->expired()->create();
        Subscription::factory()->count(4)->cancelled()->create();

        $technician = $this->userWithRole(Role::TECHNICIAN);

        foreach ([['suspended', 3], ['expired', 1], ['cancelled', 4]] as [$status, $expected]) {
            $this->actingAs($technician)
                ->get(route('services.index', ['status' => $status]))
                ->assertOk()
                ->assertViewHas('services', fn ($services) => $services->total() === $expected);
        }
    }

    public function test_an_unknown_status_falls_back_to_active_rather_than_erroring(): void
    {
        Subscription::factory()->count(2)->create(['status' => SubscriptionStatus::Active]);

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('services.index', ['status' => 'not-a-status']))
            ->assertOk()
            ->assertViewHas('status', SubscriptionStatus::Active);
    }

    public function test_the_board_counts_every_status(): void
    {
        Subscription::factory()->count(2)->create(['status' => SubscriptionStatus::Active]);
        Subscription::factory()->count(3)->suspended()->create();

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('services.index'))
            ->assertViewHas('counts', fn (array $counts) => $counts['active'] === 2
                && $counts['suspended'] === 3
                && $counts['cancelled'] === 0);
    }

    public function test_the_board_can_be_searched_by_customer_and_subscription(): void
    {
        $target = Customer::factory()->create(['last_name' => 'Villanueva']);
        Subscription::factory()->for($target)->create(['status' => SubscriptionStatus::Active]);
        Subscription::factory()->count(3)->create(['status' => SubscriptionStatus::Active]);

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('services.index', ['search' => 'Villanueva']))
            ->assertOk()
            ->assertViewHas('services', fn ($services) => $services->total() === 1);
    }

    public function test_the_board_can_be_filtered_by_plan(): void
    {
        $wanted = Subscription::factory()->create(['status' => SubscriptionStatus::Active]);
        Subscription::factory()->count(2)->create(['status' => SubscriptionStatus::Active]);

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('services.index', ['plan' => $wanted->internet_plan_id]))
            ->assertOk()
            ->assertViewHas('services', fn ($services) => $services->total() === 1);
    }

    // -----------------------------------------------------------------
    // Activate, suspend, reactivate
    // -----------------------------------------------------------------

    public function test_a_technician_can_suspend_and_then_reconnect_a_service(): void
    {
        $technician = $this->userWithRole(Role::TECHNICIAN);
        $subscription = Subscription::factory()->create(['status' => SubscriptionStatus::Active]);

        $this->actingAs($technician)->patch(route('subscriptions.status', $subscription), [
            'status' => SubscriptionStatus::Suspended->value,
            'reason' => 'Non-payment',
        ])->assertRedirect();

        $this->assertSame(SubscriptionStatus::Suspended, $subscription->refresh()->status);

        $this->actingAs($technician)->patch(route('subscriptions.status', $subscription), [
            'status' => SubscriptionStatus::Active->value,
            'reason' => 'Settled',
        ]);

        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
    }

    public function test_activating_a_pending_service_stamps_its_activation_date(): void
    {
        $subscription = Subscription::factory()->pending()->create();

        $this->assertNull($subscription->activation_date);

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->patch(route('subscriptions.status', $subscription), [
                'status' => SubscriptionStatus::Active->value,
            ]);

        $this->assertNotNull($subscription->refresh()->activation_date);
    }

    public function test_an_illegal_transition_is_refused(): void
    {
        $subscription = Subscription::factory()->pending()->create();

        // Pending goes to active or cancelled; it cannot be suspended.
        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->patch(route('subscriptions.status', $subscription), [
                'status' => SubscriptionStatus::Suspended->value,
            ])->assertSessionHas('error');

        $this->assertSame(SubscriptionStatus::Pending, $subscription->refresh()->status);
    }

    public function test_a_cancelled_service_cannot_be_moved_again(): void
    {
        $subscription = Subscription::factory()->cancelled()->create();

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->patch(route('subscriptions.status', $subscription), [
                'status' => SubscriptionStatus::Active->value,
            ])->assertForbidden();

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->refresh()->status);
    }

    public function test_suspending_the_only_service_marks_the_customer_disconnected(): void
    {
        $customer = Customer::factory()->create([
            'connection_status' => CustomerConnectionStatus::Connected,
        ]);
        $subscription = Subscription::factory()->for($customer)->create([
            'status' => SubscriptionStatus::Active,
            'activation_date' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->patch(route('subscriptions.status', $subscription), [
                'status' => SubscriptionStatus::Suspended->value,
            ]);

        $this->assertSame(CustomerConnectionStatus::Disconnected, $customer->refresh()->connection_status);
    }

    public function test_a_customer_stays_connected_while_another_service_is_active(): void
    {
        $customer = Customer::factory()->create();
        $first = Subscription::factory()->for($customer)->create(['status' => SubscriptionStatus::Active]);
        Subscription::factory()->for($customer)->create(['status' => SubscriptionStatus::Active]);

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->patch(route('subscriptions.status', $first), [
                'status' => SubscriptionStatus::Suspended->value,
            ]);

        $this->assertSame(CustomerConnectionStatus::Connected, $customer->refresh()->connection_status);
    }

    // -----------------------------------------------------------------
    // Status history and audit trail
    // -----------------------------------------------------------------

    public function test_every_status_change_is_written_to_the_history(): void
    {
        $technician = $this->userWithRole(Role::TECHNICIAN);
        $subscription = Subscription::factory()->create(['status' => SubscriptionStatus::Active]);

        $this->actingAs($technician)->patch(route('subscriptions.status', $subscription), [
            'status' => SubscriptionStatus::Suspended->value,
            'reason' => 'Non-payment',
        ]);

        $log = ServiceStatusLog::latest('id')->first();

        $this->assertSame('active', $log->from_status);
        $this->assertSame('suspended', $log->to_status);
        $this->assertSame('Non-payment', $log->reason);
        $this->assertSame($technician->id, $log->changed_by);
        $this->assertFalse($log->is_automatic);
        $this->assertSame($subscription->customer_id, $log->customer_id);
    }

    public function test_the_history_page_lists_changes_across_customers(): void
    {
        ServiceStatusLog::factory()->count(4)->create();

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('services.history'))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 4);
    }

    public function test_the_history_can_be_filtered_by_target_status(): void
    {
        ServiceStatusLog::factory()->count(3)->create(['to_status' => 'suspended']);
        ServiceStatusLog::factory()->count(2)->create(['to_status' => 'active']);

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('services.history', ['to_status' => 'suspended']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 3);
    }

    public function test_the_history_separates_scheduler_changes_from_people(): void
    {
        ServiceStatusLog::factory()->count(2)->automatic()->create();
        ServiceStatusLog::factory()->count(3)->create(['is_automatic' => false]);

        $technician = $this->userWithRole(Role::TECHNICIAN);

        $this->actingAs($technician)->get(route('services.history', ['source' => 'automatic']))
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 2);

        $this->actingAs($technician)->get(route('services.history', ['source' => 'manual']))
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 3);
    }

    public function test_the_history_can_be_filtered_by_date_range(): void
    {
        ServiceStatusLog::factory()->create(['created_at' => now()->subDays(40)]);
        ServiceStatusLog::factory()->count(2)->create(['created_at' => now()->subDay()]);

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('services.history', ['from' => now()->subWeek()->toDateString()]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 2);
    }

    public function test_the_history_can_be_searched_by_customer(): void
    {
        $customer = Customer::factory()->create(['last_name' => 'Villanueva']);
        $subscription = Subscription::factory()->for($customer)->create();

        ServiceStatusLog::factory()->forSubscription($subscription)->create();
        ServiceStatusLog::factory()->count(3)->create();

        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('services.history', ['search' => 'Villanueva']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
    }

    // -----------------------------------------------------------------
    // Provisioning seam
    // -----------------------------------------------------------------

    public function test_no_network_backend_is_wired_up_by_default(): void
    {
        $this->assertInstanceOf(NullServiceProvisioner::class, app(ServiceProvisioner::class));
        $this->assertFalse(app(ServiceProvisioner::class)->isEnabled());
    }

    public function test_activating_a_service_asks_the_provisioner_to_bring_the_line_up(): void
    {
        $provisioner = Mockery::mock(ServiceProvisioner::class);
        $provisioner->shouldReceive('activate')->once();
        $provisioner->shouldNotReceive('suspend');
        $provisioner->shouldNotReceive('terminate');
        $this->app->instance(ServiceProvisioner::class, $provisioner);

        app(SubscriptionService::class)->changeStatus(
            Subscription::factory()->pending()->create(),
            SubscriptionStatus::Active,
            null,
        );
    }

    public function test_suspending_and_cancelling_map_to_the_right_provisioner_calls(): void
    {
        $provisioner = Mockery::mock(ServiceProvisioner::class);
        $provisioner->shouldReceive('suspend')->once();
        $provisioner->shouldReceive('terminate')->once();
        $this->app->instance(ServiceProvisioner::class, $provisioner);

        $service = app(SubscriptionService::class);
        $subscription = Subscription::factory()->create(['status' => SubscriptionStatus::Active]);

        $service->changeStatus($subscription, SubscriptionStatus::Suspended, null);
        $service->changeStatus($subscription->refresh(), SubscriptionStatus::Cancelled, null);
    }

    public function test_a_provisioning_failure_does_not_undo_the_recorded_status_change(): void
    {
        // The status change is already committed by the time the network is
        // told about it, so a device error must not roll it back.
        $provisioner = Mockery::mock(ServiceProvisioner::class);
        $provisioner->shouldReceive('suspend')->andThrow(new \RuntimeException('router unreachable'));
        $this->app->instance(ServiceProvisioner::class, $provisioner);

        $subscription = Subscription::factory()->create(['status' => SubscriptionStatus::Active]);

        app(SubscriptionService::class)->changeStatus($subscription, SubscriptionStatus::Suspended, 'Non-payment');

        $this->assertSame(SubscriptionStatus::Suspended, $subscription->refresh()->status);
        $this->assertDatabaseHas('service_status_logs', [
            'subscription_id' => $subscription->id,
            'to_status' => 'suspended',
        ]);
    }

    public function test_an_illegal_transition_never_reaches_the_provisioner(): void
    {
        $provisioner = Mockery::mock(ServiceProvisioner::class);
        $provisioner->shouldNotReceive('activate');
        $provisioner->shouldNotReceive('suspend');
        $provisioner->shouldNotReceive('terminate');
        $this->app->instance(ServiceProvisioner::class, $provisioner);

        $this->expectException(DomainException::class);

        app(SubscriptionService::class)->changeStatus(
            Subscription::factory()->pending()->create(),
            SubscriptionStatus::Suspended,
            null,
        );
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_billing_staff_can_see_services_but_not_change_them(): void
    {
        $staff = $this->userWithRole(Role::BILLING_STAFF);
        $subscription = Subscription::factory()->create(['status' => SubscriptionStatus::Active]);

        $this->actingAs($staff)->get(route('services.index'))->assertOk();
        $this->actingAs($staff)->get(route('services.history'))->assertOk();

        $this->actingAs($staff)->patch(route('subscriptions.status', $subscription), [
            'status' => SubscriptionStatus::Suspended->value,
        ])->assertForbidden();

        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
    }

    public function test_an_accountant_cannot_reach_service_management(): void
    {
        $accountant = $this->userWithRole(Role::ACCOUNTANT);

        $this->actingAs($accountant)->get(route('services.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('services.history'))->assertForbidden();
    }
}
