<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\BillingCycle;
use App\Models\InternetPlan;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleAndPermissionSeeder::class, SystemSettingSeeder::class]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->value('id'));

        return $user->fresh();
    }

    private function activeSubscription(): Subscription
    {
        $plan = InternetPlan::factory()->priced(1499)->create();

        return Subscription::factory()->forPlan($plan)->create([
            'start_date' => now()->subYear()->toDateString(),
            'billing_day' => 5,
            'monthly_rate' => '1499.00',
            'installation_fee' => '0.00',
            'discount_amount' => '0.00',
        ]);
    }

    public function test_the_billing_pages_render(): void
    {
        $staff = $this->userWithRole(Role::BILLING_STAFF);
        $cycle = BillingCycle::factory()->create();

        $this->actingAs($staff)->get(route('billing.index'))->assertOk()->assertSee('Billing cycles');
        $this->actingAs($staff)->get(route('billing.show', $cycle))->assertOk()->assertSee($cycle->name);
    }

    public function test_a_billing_cycle_can_be_opened_for_a_month(): void
    {
        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('billing.store'), ['month' => '2026-08'])
            ->assertRedirect();

        $cycle = BillingCycle::first();

        $this->assertSame('2026-08-01', $cycle->period_start->format('Y-m-d'));
        $this->assertSame('2026-08-31', $cycle->period_end->format('Y-m-d'));
        $this->assertSame('August 2026', $cycle->name);
    }

    public function test_opening_a_month_that_already_exists_does_not_duplicate_it(): void
    {
        $staff = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($staff)->post(route('billing.store'), ['month' => '2026-08']);
        $this->actingAs($staff)->post(route('billing.store'), ['month' => '2026-08']);

        $this->assertSame(1, BillingCycle::count());
    }

    public function test_a_month_is_required_to_open_a_cycle(): void
    {
        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('billing.store'), ['month' => 'not-a-month'])
            ->assertSessionHasErrors('month');
    }

    public function test_generating_a_cycle_issues_invoices(): void
    {
        $this->activeSubscription();
        $this->activeSubscription();
        $cycle = BillingCycle::factory()->forMonth(now())->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('billing.generate', $cycle))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $m) => str_contains($m, '2 invoice(s) created'));

        $this->assertSame(2, Invoice::count());
    }

    public function test_pressing_generate_twice_does_not_duplicate_invoices(): void
    {
        $this->activeSubscription();
        $cycle = BillingCycle::factory()->forMonth(now())->create();
        $staff = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($staff)->post(route('billing.generate', $cycle));
        $this->actingAs($staff)->post(route('billing.generate', $cycle))
            ->assertSessionHas('success', fn (string $m) => str_contains($m, '0 invoice(s) created, 1 skipped'));

        $this->assertSame(1, Invoice::count());
    }

    public function test_generated_invoices_are_linked_to_the_cycle_and_subscription(): void
    {
        $subscription = $this->activeSubscription();
        $cycle = BillingCycle::factory()->forMonth(now())->create();

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('billing.generate', $cycle));

        $invoice = Invoice::first();

        $this->assertSame($cycle->id, $invoice->billing_cycle_id);
        $this->assertSame($subscription->id, $invoice->subscription_id);
        $this->assertSame($subscription->customer_id, $invoice->customer_id);
        $this->assertSame(
            $cycle->period_start->format('Y-m-d'),
            $invoice->billing_period_start->format('Y-m-d')
        );
    }

    public function test_overdue_invoices_can_be_marked_from_the_billing_screen(): void
    {
        Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid, 'due_date' => now()->subDays(10), 'balance_due' => 900,
        ]);

        $this->actingAs($this->userWithRole(Role::BILLING_STAFF))
            ->post(route('billing.mark-overdue'))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $m) => str_contains($m, '1 invoice(s) marked overdue'));

        $this->assertSame(InvoiceStatus::Overdue, Invoice::first()->status);
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_an_accountant_may_not_reach_billing(): void
    {
        $accountant = $this->userWithRole(Role::ACCOUNTANT);
        $cycle = BillingCycle::factory()->create();

        $this->actingAs($accountant)->get(route('billing.index'))->assertForbidden();
        $this->actingAs($accountant)->post(route('billing.generate', $cycle))->assertForbidden();
    }

    public function test_a_technician_may_not_generate_invoices(): void
    {
        $technician = $this->userWithRole(Role::TECHNICIAN);
        $cycle = BillingCycle::factory()->create();

        $this->actingAs($technician)->post(route('billing.generate', $cycle))->assertForbidden();
        $this->assertSame(0, Invoice::count());
    }
}
