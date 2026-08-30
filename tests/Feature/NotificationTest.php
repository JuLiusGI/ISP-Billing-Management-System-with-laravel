<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Notifications\InvoiceGenerated;
use App\Notifications\InvoiceOverdue;
use App\Notifications\PaymentReceived;
use App\Notifications\ServiceReactivated;
use App\Notifications\ServiceSuspended;
use App\Services\BillingService;
use App\Services\PaymentService;
use App\Services\SettingsService;
use App\Services\SubscriptionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(SystemSettingSeeder::class);

        Notification::fake();
    }

    /** Turns the master switch on; the per-event switches are seeded on. */
    private function enableNotifications(): void
    {
        app(SettingsService::class)->set('notifications.email_enabled', true);
    }

    private function customerWithEmail(): Customer
    {
        return Customer::factory()->create(['email' => 'customer@example.test']);
    }

    // -----------------------------------------------------------------
    // The gate
    // -----------------------------------------------------------------

    public function test_nothing_is_sent_while_the_master_switch_is_off(): void
    {
        // Seeded default is off, which is what a fresh install should do.
        $customer = $this->customerWithEmail();

        app(PaymentService::class)->record($customer, [
            'amount' => '500.00', 'payment_method' => 'cash',
        ], []);

        Notification::assertNothingSent();
    }

    public function test_a_single_event_can_be_switched_off_independently(): void
    {
        $this->enableNotifications();
        app(SettingsService::class)->set('notifications.on_payment_received', false);

        $customer = $this->customerWithEmail();

        app(PaymentService::class)->record($customer, [
            'amount' => '500.00', 'payment_method' => 'cash',
        ], []);

        Notification::assertNothingSent();
    }

    public function test_a_customer_without_an_email_is_skipped(): void
    {
        $this->enableNotifications();
        $customer = Customer::factory()->create(['email' => null]);

        app(PaymentService::class)->record($customer, [
            'amount' => '500.00', 'payment_method' => 'cash',
        ], []);

        Notification::assertNothingSent();
    }

    // -----------------------------------------------------------------
    // Dispatch points
    // -----------------------------------------------------------------

    public function test_recording_a_payment_notifies_the_customer(): void
    {
        $this->enableNotifications();
        $customer = $this->customerWithEmail();

        app(PaymentService::class)->record($customer, [
            'amount' => '1500.00', 'payment_method' => 'gcash',
        ], []);

        Notification::assertSentTo($customer, PaymentReceived::class);
    }

    public function test_issuing_an_invoice_notifies_the_customer(): void
    {
        $this->enableNotifications();

        $customer = $this->customerWithEmail();
        $subscription = Subscription::factory()->for($customer)->create([
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subYear(),
            'billing_day' => 1,
        ]);

        $billing = app(BillingService::class);
        $cycle = $billing->cycleFor(now());
        $billing->generateFor($subscription, $cycle);

        Notification::assertSentTo($customer, InvoiceGenerated::class);
    }

    public function test_marking_invoices_overdue_notifies_each_customer(): void
    {
        $this->enableNotifications();

        $customer = $this->customerWithEmail();
        Invoice::factory()->for($customer)->create([
            'status' => InvoiceStatus::Unpaid,
            'balance_due' => 900,
            'due_date' => now()->subDays(10),
        ]);

        $marked = app(BillingService::class)->markOverdueInvoices();

        $this->assertSame(1, $marked);
        Notification::assertSentTo($customer, InvoiceOverdue::class);
        $this->assertSame(InvoiceStatus::Overdue, Invoice::first()->status);
    }

    public function test_suspending_a_service_notifies_the_customer(): void
    {
        $this->enableNotifications();

        $customer = $this->customerWithEmail();
        $subscription = Subscription::factory()->for($customer)->create([
            'status' => SubscriptionStatus::Active,
        ]);

        app(SubscriptionService::class)->changeStatus(
            $subscription, SubscriptionStatus::Suspended, 'Non-payment'
        );

        Notification::assertSentTo(
            $customer,
            ServiceSuspended::class,
            fn (ServiceSuspended $n) => $n->reason === 'Non-payment'
        );
    }

    public function test_restoring_a_suspended_service_notifies_the_customer(): void
    {
        $this->enableNotifications();

        $customer = $this->customerWithEmail();
        $subscription = Subscription::factory()->for($customer)->suspended()->create();

        app(SubscriptionService::class)->changeStatus($subscription, SubscriptionStatus::Active, 'Settled');

        Notification::assertSentTo($customer, ServiceReactivated::class);
    }

    public function test_activating_a_pending_service_is_not_a_reactivation(): void
    {
        $this->enableNotifications();

        $customer = $this->customerWithEmail();
        $subscription = Subscription::factory()->for($customer)->pending()->create();

        app(SubscriptionService::class)->changeStatus($subscription, SubscriptionStatus::Active, null);

        // A first activation is not "your service is back on".
        Notification::assertNotSentTo($customer, ServiceReactivated::class);
    }

    public function test_an_administrative_status_move_sends_nothing(): void
    {
        $this->enableNotifications();

        $customer = $this->customerWithEmail();
        $subscription = Subscription::factory()->for($customer)->create([
            'status' => SubscriptionStatus::Active,
        ]);

        app(SubscriptionService::class)->changeStatus($subscription, SubscriptionStatus::Expired, null);

        Notification::assertNothingSent();
    }

    // -----------------------------------------------------------------
    // Content
    // -----------------------------------------------------------------

    public function test_mail_is_signed_off_with_the_configured_isp_name(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('company.name', 'Samar Fiber Networks');
        $settings->set('company.phone', '(055) 251-0000');

        $customer = $this->customerWithEmail();
        $payment = Payment::factory()->for($customer)->ofAmount(750)->create();

        $mail = (new PaymentReceived($payment))->toMail($customer);
        $rendered = json_encode($mail->toArray());

        $this->assertStringContainsString('Samar Fiber Networks', $rendered);
        $this->assertStringContainsString('(055) 251-0000', $rendered);
    }

    public function test_the_overdue_notice_only_warns_about_suspension_where_it_happens(): void
    {
        $settings = app(SettingsService::class);
        $customer = $this->customerWithEmail();
        $invoice = Invoice::factory()->for($customer)->create([
            'status' => InvoiceStatus::Overdue, 'balance_due' => 1200,
            'due_date' => now()->subDays(20),
        ]);

        $settings->set('service.auto_suspend_enabled', false);
        $without = json_encode((new InvoiceOverdue($invoice))->toMail($customer)->toArray());
        $this->assertStringNotContainsString('suspended automatically', $without);

        $settings->set('service.auto_suspend_enabled', true);
        $with = json_encode((new InvoiceOverdue($invoice))->toMail($customer)->toArray());
        $this->assertStringContainsString('suspended automatically', $with);
    }

    public function test_a_payment_notice_states_the_remaining_balance(): void
    {
        $customer = $this->customerWithEmail();
        Invoice::factory()->for($customer)->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 400,
        ]);
        $payment = Payment::factory()->for($customer)->ofAmount(600)->create();

        $rendered = json_encode((new PaymentReceived($payment))->toMail($customer->refresh())->toArray());

        $this->assertStringContainsString('Remaining balance', $rendered);
        $this->assertStringContainsString('400.00', $rendered);
    }

    public function test_a_settled_account_is_told_so_rather_than_shown_a_zero(): void
    {
        $customer = $this->customerWithEmail();
        $payment = Payment::factory()->for($customer)->ofAmount(600)->create();

        $rendered = json_encode((new PaymentReceived($payment))->toMail($customer)->toArray());

        $this->assertStringContainsString('fully settled', $rendered);
    }

    // -----------------------------------------------------------------
    // Failure handling
    // -----------------------------------------------------------------

    public function test_a_notification_failure_does_not_roll_back_the_payment(): void
    {
        $this->enableNotifications();

        // A mail transport that throws must not cost the ISP the record of
        // money it has taken.
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('smtp down'));

        $customer = $this->customerWithEmail();

        $payment = app(PaymentService::class)->record($customer, [
            'amount' => '2500.00', 'payment_method' => 'cash',
        ], []);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'amount' => '2500.00',
        ]);
    }
}
