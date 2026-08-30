<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceStatusLog;
use App\Models\Subscription;
use App\Services\SettingsService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AutomatedBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(SystemSettingSeeder::class);

        Notification::fake();
    }

    private function settings(): SettingsService
    {
        app()->forgetInstance(SettingsService::class);

        return app(SettingsService::class);
    }

    private function billableSubscription(array $overrides = []): Subscription
    {
        return Subscription::factory()->create(array_merge([
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subYear(),
            'billing_day' => 1,
            'monthly_rate' => 1500,
            'discount_amount' => 0,
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // billing:generate-invoices
    // -----------------------------------------------------------------

    public function test_the_command_issues_invoices_for_billable_subscriptions(): void
    {
        $this->billableSubscription();
        $this->billableSubscription();

        $this->artisan('billing:generate-invoices')->assertSuccessful();

        $this->assertSame(2, Invoice::count());
    }

    public function test_running_it_twice_does_not_double_invoice(): void
    {
        $this->billableSubscription();

        $this->artisan('billing:generate-invoices')->assertSuccessful();
        $this->artisan('billing:generate-invoices')->assertSuccessful();

        // The second run finds the subscription already invoiced for the
        // period and skips it.
        $this->assertSame(1, Invoice::count());
    }

    public function test_a_dry_run_issues_nothing(): void
    {
        $this->billableSubscription();

        $this->artisan('billing:generate-invoices --dry-run')
            ->expectsOutputToContain('would be issued')
            ->assertSuccessful();

        $this->assertSame(0, Invoice::count());
    }

    public function test_a_specific_month_can_be_billed(): void
    {
        $this->billableSubscription(['start_date' => now()->subYears(2)]);

        $month = now()->subMonths(3);

        $this->artisan('billing:generate-invoices --month='.$month->format('Y-m'))
            ->assertSuccessful();

        $invoice = Invoice::first();

        $this->assertSame(
            $month->copy()->startOfMonth()->toDateString(),
            $invoice->billing_period_start->toDateString(),
        );
    }

    public function test_a_malformed_month_is_rejected_rather_than_guessed(): void
    {
        $this->artisan('billing:generate-invoices --month=august')
            ->expectsOutputToContain('YYYY-MM')
            ->assertFailed();

        $this->assertSame(0, Invoice::count());
    }

    public function test_inactive_subscriptions_are_not_billed(): void
    {
        $this->billableSubscription();
        Subscription::factory()->suspended()->create(['start_date' => now()->subYear()]);
        Subscription::factory()->cancelled()->create(['start_date' => now()->subYear()]);

        $this->artisan('billing:generate-invoices')->assertSuccessful();

        $this->assertSame(1, Invoice::count());
    }

    // -----------------------------------------------------------------
    // billing:update-overdue
    // -----------------------------------------------------------------

    public function test_the_overdue_sweep_marks_only_what_is_past_due(): void
    {
        Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 500, 'due_date' => now()->subDays(3),
        ]);
        Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 500, 'due_date' => now()->addDays(3),
        ]);

        $this->artisan('billing:update-overdue')->assertSuccessful();

        $this->assertSame(1, Invoice::where('status', InvoiceStatus::Overdue)->count());
    }

    public function test_the_overdue_sweep_is_idempotent(): void
    {
        Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 500, 'due_date' => now()->subDays(3),
        ]);

        $this->artisan('billing:update-overdue')->assertSuccessful();

        // The second run finds nothing left to mark.
        $this->artisan('billing:update-overdue')
            ->expectsOutputToContain('No invoices became overdue')
            ->assertSuccessful();
    }

    public function test_a_settled_invoice_is_never_marked_overdue(): void
    {
        Invoice::factory()->create([
            'status' => InvoiceStatus::Paid, 'balance_due' => 0, 'due_date' => now()->subMonth(),
        ]);
        Invoice::factory()->cancelled()->create(['due_date' => now()->subMonth()]);

        $this->artisan('billing:update-overdue')->assertSuccessful();

        $this->assertSame(0, Invoice::where('status', InvoiceStatus::Overdue)->count());
    }

    // -----------------------------------------------------------------
    // billing:process-service-status
    // -----------------------------------------------------------------

    public function test_nothing_is_suspended_while_automatic_suspension_is_off(): void
    {
        $subscription = $this->overdueCustomerWithService(40);

        // Seeded default is off, which is the safe default for a fresh install.
        $this->artisan('billing:process-service-status')
            ->expectsOutputToContain('Automatic suspension is disabled')
            ->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
    }

    public function test_overdue_services_are_suspended_once_it_is_enabled(): void
    {
        $this->settings()->set('service.auto_suspend_enabled', true);
        $subscription = $this->overdueCustomerWithService(40);

        $this->artisan('billing:process-service-status')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Suspended, $subscription->refresh()->status);
    }

    public function test_the_configured_threshold_is_respected(): void
    {
        $settings = $this->settings();
        $settings->set('service.auto_suspend_enabled', true);
        $settings->set('service.suspend_after_days_overdue', 30);

        $justInside = $this->overdueCustomerWithService(35);
        $notYet = $this->overdueCustomerWithService(10);

        $this->artisan('billing:process-service-status')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Suspended, $justInside->refresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $notYet->refresh()->status);
    }

    public function test_an_automatic_suspension_is_logged_as_automatic(): void
    {
        $this->settings()->set('service.auto_suspend_enabled', true);
        $subscription = $this->overdueCustomerWithService(40);

        $this->artisan('billing:process-service-status')->assertSuccessful();

        $log = ServiceStatusLog::where('subscription_id', $subscription->id)
            ->where('to_status', 'suspended')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertTrue($log->is_automatic, 'The scheduler must be distinguishable from a person.');
        $this->assertNull($log->changed_by);
        $this->assertStringContainsString('Automatically suspended', $log->reason);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $this->settings()->set('service.auto_suspend_enabled', true);
        $subscription = $this->overdueCustomerWithService(40);

        $this->artisan('billing:process-service-status --dry-run')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
        $this->assertSame(0, ServiceStatusLog::where('to_status', 'suspended')->count());
    }

    public function test_running_it_twice_does_not_suspend_twice(): void
    {
        $this->settings()->set('service.auto_suspend_enabled', true);
        $subscription = $this->overdueCustomerWithService(40);

        $this->artisan('billing:process-service-status')->assertSuccessful();
        $this->artisan('billing:process-service-status')->assertSuccessful();

        $this->assertSame(
            1,
            ServiceStatusLog::where('subscription_id', $subscription->id)
                ->where('to_status', 'suspended')->count(),
        );
    }

    public function test_a_customer_in_good_standing_is_left_alone(): void
    {
        $this->settings()->set('service.auto_suspend_enabled', true);

        $customer = Customer::factory()->create();
        $subscription = Subscription::factory()->for($customer)->create([
            'status' => SubscriptionStatus::Active,
        ]);
        Invoice::factory()->for($customer)->create([
            'status' => InvoiceStatus::Paid, 'balance_due' => 0, 'due_date' => now()->subMonths(3),
        ]);

        $this->artisan('billing:process-service-status')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
    }

    public function test_lapsed_services_expire_regardless_of_the_suspension_switch(): void
    {
        // Expiry is a fact, not a policy decision, so it is not configurable.
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'expiration_date' => now()->subDays(5),
        ]);

        $this->artisan('billing:process-service-status')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Expired, $subscription->refresh()->status);
    }

    public function test_a_service_expiring_in_the_future_is_untouched(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'expiration_date' => now()->addMonth(),
        ]);

        $this->artisan('billing:process-service-status')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
    }

    public function test_an_automatic_suspension_reaches_the_audit_trail(): void
    {
        $this->settings()->set('service.auto_suspend_enabled', true);
        $this->overdueCustomerWithService(40);

        $this->artisan('billing:process-service-status')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Subscriptions',
            'action' => 'service_status_changed',
            // No user: the scheduler did it.
            'user_id' => null,
        ]);
    }

    // -----------------------------------------------------------------
    // The schedule itself
    // -----------------------------------------------------------------

    public function test_all_three_commands_are_scheduled_without_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events());

        foreach ([
            'billing:generate-invoices',
            'billing:update-overdue',
            'billing:process-service-status',
        ] as $command) {
            $event = $events->first(fn ($e) => str_contains($e->command ?? '', $command));

            $this->assertNotNull($event, "{$command} should be scheduled.");
            $this->assertTrue(
                $event->withoutOverlapping,
                "{$command} should not be able to start on top of a slow previous run."
            );
        }
    }

    public function test_the_schedule_runs_invoicing_before_the_overdue_sweep(): void
    {
        $events = collect(app(Schedule::class)->events());

        $at = fn (string $command) => $events
            ->first(fn ($e) => str_contains($e->command ?? '', $command))
            ->expression;

        // Invoices at 01:00, overdue at 02:00, service status at 03:00, so a
        // line is never suspended over an invoice not yet marked overdue.
        $this->assertSame('0 1 * * *', $at('billing:generate-invoices'));
        $this->assertSame('0 2 * * *', $at('billing:update-overdue'));
        $this->assertSame('0 3 * * *', $at('billing:process-service-status'));
    }

    // -----------------------------------------------------------------

    /** An active service whose customer has an invoice $days past due. */
    private function overdueCustomerWithService(int $days): Subscription
    {
        $customer = Customer::factory()->create();

        Invoice::factory()->for($customer)->create([
            'status' => InvoiceStatus::Overdue,
            'balance_due' => 1500,
            'due_date' => Carbon::now()->subDays($days),
        ]);

        return Subscription::factory()->for($customer)->create([
            'status' => SubscriptionStatus::Active,
        ]);
    }
}
