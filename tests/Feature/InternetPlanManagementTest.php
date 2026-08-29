<?php

namespace Tests\Feature;

use App\Models\InternetPlan;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternetPlanManagementTest extends TestCase
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
        return array_replace([
            'plan_code' => 'HOME-150',
            'name' => 'Home 150 Mbps',
            'download_speed' => 150,
            'upload_speed' => 150,
            'speed_unit' => 'Mbps',
            'monthly_price' => '1799.00',
            'installation_fee' => '1500.00',
            'activation_fee' => '0.00',
            'billing_cycle' => 'monthly',
            'description' => 'Mid-tier fibre plan.',
            'is_active' => '1',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Listing and forms
    // -----------------------------------------------------------------

    public function test_the_plan_list_renders(): void
    {
        InternetPlan::factory()->count(3)->create();

        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->get(route('plans.index'))
            ->assertOk()
            ->assertSee('Internet plans');
    }

    public function test_the_create_and_edit_forms_render(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);
        $plan = InternetPlan::factory()->create(['name' => 'Existing Plan']);

        $this->actingAs($admin)->get(route('plans.create'))->assertOk()->assertSee('New internet plan');
        $this->actingAs($admin)->get(route('plans.edit', $plan))->assertOk()->assertSee('Existing Plan');
    }

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        InternetPlan::factory()->create(['name' => 'Fibre Premium', 'plan_code' => 'FIB-PREM']);
        InternetPlan::factory()->count(3)->create(['is_active' => false]);

        $admin = $this->userWithRole(Role::ADMINISTRATOR);

        $this->actingAs($admin)->get(route('plans.index', ['search' => 'FIB-PREM']))
            ->assertOk()->assertViewHas('plans', fn ($plans) => $plans->total() === 1);

        $this->actingAs($admin)->get(route('plans.index', ['status' => 'inactive']))
            ->assertOk()->assertViewHas('plans', fn ($plans) => $plans->total() === 3);
    }

    public function test_the_list_shows_active_subscriber_counts(): void
    {
        $plan = InternetPlan::factory()->create();
        Subscription::factory()->count(2)->forPlan($plan)->create();
        Subscription::factory()->forPlan($plan)->suspended()->create();

        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->get(route('plans.index'))
            ->assertOk()
            ->assertViewHas('plans', function ($plans) {
                $row = $plans->first();

                return $row->subscriptions_count === 3 && $row->active_subscriptions_count === 2;
            });
    }

    // -----------------------------------------------------------------
    // Creating and validating
    // -----------------------------------------------------------------

    public function test_a_plan_can_be_created(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->post(route('plans.store'), $this->validPayload())
            ->assertRedirect(route('plans.index'));

        $plan = InternetPlan::where('plan_code', 'HOME-150')->first();

        $this->assertNotNull($plan);
        $this->assertSame('1799.00', $plan->monthly_price);
        $this->assertTrue($plan->is_active);
    }

    public function test_the_plan_code_is_normalised_to_uppercase(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->post(route('plans.store'), $this->validPayload(['plan_code' => '  home-999  ']));

        $this->assertDatabaseHas('internet_plans', ['plan_code' => 'HOME-999']);
    }

    public function test_duplicate_plan_codes_are_rejected(): void
    {
        InternetPlan::factory()->create(['plan_code' => 'HOME-150']);

        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->post(route('plans.store'), $this->validPayload())
            ->assertSessionHasErrors('plan_code');
    }

    public function test_required_and_numeric_rules_are_enforced(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))->post(route('plans.store'), [
            'plan_code' => 'BAD CODE!',
            'name' => '',
            'download_speed' => 'fast',
            'upload_speed' => -5,
            'speed_unit' => 'Tbps',
            'monthly_price' => -1,
            'billing_cycle' => 'fortnightly',
            'is_active' => '1',
        ])->assertSessionHasErrors([
            'plan_code', 'name', 'download_speed', 'upload_speed',
            'speed_unit', 'monthly_price', 'billing_cycle',
            'installation_fee', 'activation_fee',
        ]);

        $this->assertSame(0, InternetPlan::count());
    }

    public function test_a_price_with_three_decimal_places_is_rejected(): void
    {
        // Money is DECIMAL(12,2); a third place would be silently rounded.
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->post(route('plans.store'), $this->validPayload(['monthly_price' => '1499.999']))
            ->assertSessionHasErrors('monthly_price');
    }

    // -----------------------------------------------------------------
    // Historical pricing independence
    // -----------------------------------------------------------------

    public function test_repricing_a_plan_does_not_touch_existing_subscriptions(): void
    {
        $plan = InternetPlan::factory()->priced(1500)->create(['plan_code' => 'HOME-150']);
        $subscription = Subscription::factory()->forPlan($plan)->create();

        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))->put(
            route('plans.update', $plan),
            $this->validPayload(['monthly_price' => '2500.00'])
        )->assertRedirect();

        $this->assertSame('2500.00', $plan->refresh()->monthly_price);
        $this->assertSame('1500.00', $subscription->refresh()->monthly_rate);
    }

    public function test_repricing_a_plan_does_not_touch_issued_invoices(): void
    {
        $plan = InternetPlan::factory()->priced(1500)->create(['plan_code' => 'HOME-150']);
        $subscription = Subscription::factory()->forPlan($plan)->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'subtotal' => 1500, 'total_amount' => 1500, 'balance_due' => 1500,
        ]);

        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))->put(
            route('plans.update', $plan),
            $this->validPayload(['monthly_price' => '9999.00'])
        );

        $this->assertSame('1500.00', $invoice->refresh()->total_amount);
        $this->assertSame('1500.00', $invoice->items->first()->line_total);
    }

    public function test_repricing_warns_that_existing_subscriptions_are_unaffected(): void
    {
        $plan = InternetPlan::factory()->priced(1500)->create(['plan_code' => 'HOME-150']);

        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->put(route('plans.update', $plan), $this->validPayload(['monthly_price' => '1600.00']))
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'keep the rate'));
    }

    // -----------------------------------------------------------------
    // Activation and deletion
    // -----------------------------------------------------------------

    public function test_a_plan_can_be_deactivated_and_reactivated(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);
        $plan = InternetPlan::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->patch(route('plans.toggle', $plan))->assertRedirect();
        $this->assertFalse($plan->refresh()->is_active);

        $this->actingAs($admin)->patch(route('plans.toggle', $plan));
        $this->assertTrue($plan->refresh()->is_active);
    }

    public function test_deactivating_a_plan_leaves_its_subscriptions_alone(): void
    {
        $plan = InternetPlan::factory()->create(['is_active' => true]);
        $subscription = Subscription::factory()->forPlan($plan)->create();

        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))->patch(route('plans.toggle', $plan));

        $this->assertFalse($plan->refresh()->is_active);
        $this->assertSame('active', $subscription->refresh()->status->value);
    }

    public function test_an_unused_plan_can_be_deleted(): void
    {
        $plan = InternetPlan::factory()->create();

        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->delete(route('plans.destroy', $plan))
            ->assertRedirect(route('plans.index'));

        $this->assertSoftDeleted($plan);
    }

    public function test_a_plan_in_use_cannot_be_deleted(): void
    {
        $plan = InternetPlan::factory()->create();
        Subscription::factory()->forPlan($plan)->create();

        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->delete(route('plans.destroy', $plan))
            ->assertForbidden();

        $this->assertNotSoftDeleted($plan);
    }

    public function test_a_plan_whose_subscription_was_archived_still_cannot_be_deleted(): void
    {
        $plan = InternetPlan::factory()->create();
        $subscription = Subscription::factory()->forPlan($plan)->create();
        $subscription->delete();

        // The invoice history still names this plan, so it must not vanish.
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->delete(route('plans.destroy', $plan))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_billing_staff_may_view_plans_but_not_change_them(): void
    {
        $staff = $this->userWithRole(Role::BILLING_STAFF);
        $plan = InternetPlan::factory()->create();

        $this->actingAs($staff)->get(route('plans.index'))->assertOk();
        $this->actingAs($staff)->get(route('plans.create'))->assertForbidden();
        $this->actingAs($staff)->get(route('plans.edit', $plan))->assertForbidden();
        $this->actingAs($staff)->patch(route('plans.toggle', $plan))->assertForbidden();
        $this->actingAs($staff)->delete(route('plans.destroy', $plan))->assertForbidden();
    }

    public function test_a_technician_may_view_plans(): void
    {
        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('plans.index'))
            ->assertOk();
    }

    public function test_an_accountant_cannot_reach_plans_at_all(): void
    {
        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->get(route('plans.index'))
            ->assertForbidden();
    }
}
