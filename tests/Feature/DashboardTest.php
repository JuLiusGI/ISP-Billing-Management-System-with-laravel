<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\DashboardService;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    private function dashboard(): DashboardService
    {
        return app(DashboardService::class);
    }

    // -----------------------------------------------------------------
    // Statistics
    // -----------------------------------------------------------------

    public function test_customer_statistics_count_each_status(): void
    {
        Customer::factory()->count(3)->create(['status' => CustomerStatus::Active]);
        Customer::factory()->count(2)->suspended()->create();
        Customer::factory()->create(['status' => CustomerStatus::Inactive]);

        $stats = $this->dashboard()->customerStats();

        $this->assertSame(6, $stats['total']);
        $this->assertSame(3, $stats['active']);
        $this->assertSame(2, $stats['suspended']);
        $this->assertSame(1, $stats['inactive']);
    }

    public function test_new_this_month_counts_only_this_month(): void
    {
        Customer::factory()->count(2)->create(['created_at' => now()]);
        Customer::factory()->count(4)->create(['created_at' => now()->subMonths(3)]);

        $this->assertSame(2, $this->dashboard()->customerStats()['newThisMonth']);
    }

    public function test_service_statistics_count_each_state(): void
    {
        Subscription::factory()->count(4)->create(['status' => SubscriptionStatus::Active]);
        Subscription::factory()->count(2)->suspended()->create();
        Subscription::factory()->expired()->create();
        Customer::factory()->count(3)->pendingInstallation()->create();

        $stats = $this->dashboard()->serviceStats();

        $this->assertSame(4, $stats['active']);
        $this->assertSame(2, $stats['suspended']);
        $this->assertSame(1, $stats['expired']);
        $this->assertSame(3, $stats['pendingInstallation']);
    }

    public function test_billing_statistics_exclude_cancelled_invoices(): void
    {
        Invoice::factory()->count(2)->create([
            'status' => InvoiceStatus::Unpaid, 'total_amount' => 1000,
            'amount_paid' => 0, 'balance_due' => 1000, 'due_date' => now()->addWeek(),
        ]);
        Invoice::factory()->cancelled()->create(['total_amount' => 9999, 'balance_due' => 0]);

        $stats = $this->dashboard()->billingStats();

        $this->assertSame('2000.00', $stats['totalInvoiced']);
        $this->assertSame('2000.00', $stats['totalOutstanding']);
        $this->assertSame('0.00', $stats['totalOverdue']);
    }

    public function test_overdue_total_counts_only_invoices_past_due(): void
    {
        Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 500, 'due_date' => now()->subDays(10),
        ]);
        Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 700, 'due_date' => now()->addDays(10),
        ]);

        $this->assertSame('500.00', $this->dashboard()->billingStats()['totalOverdue']);
    }

    public function test_financial_statistics_reconcile_this_month(): void
    {
        Payment::factory()->ofAmount(8000)->create(['payment_date' => now()->startOfMonth()->addDay()]);
        // Last month must not leak into the monthly figure.
        Payment::factory()->ofAmount(5000)->create(['payment_date' => now()->subMonth()]);
        Expense::factory()->create([
            'expense_category_id' => ExpenseCategory::first()->id,
            'amount' => 3000, 'expense_date' => now()->startOfMonth()->addDay(),
        ]);

        $stats = $this->dashboard()->financialStats();

        $this->assertSame('8000.00', $stats['revenueThisMonth']);
        $this->assertSame('3000.00', $stats['expensesThisMonth']);
        $this->assertSame('5000.00', $stats['netThisMonth']);
    }

    public function test_reversed_payments_are_not_counted_as_revenue(): void
    {
        Payment::factory()->ofAmount(1000)->create(['payment_date' => now()]);
        Payment::factory()->ofAmount(9999)->reversed()->create(['payment_date' => now()]);

        $this->assertSame('1000.00', $this->dashboard()->financialStats()['revenueThisMonth']);
    }

    public function test_an_empty_database_produces_zeros_rather_than_errors(): void
    {
        $stats = $this->dashboard()->financialStats();

        $this->assertSame('0.00', $stats['netThisMonth']);
        $this->assertSame(0, $this->dashboard()->customerStats()['total']);
        $this->assertSame([], $this->dashboard()->serviceMix()['values']);
    }

    // -----------------------------------------------------------------
    // Alerts
    // -----------------------------------------------------------------

    public function test_alerts_count_distinct_overdue_accounts(): void
    {
        $customer = Customer::factory()->create();
        // Two overdue invoices for one customer is one overdue account.
        Invoice::factory()->for($customer)->count(2)->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 500, 'due_date' => now()->subDays(20),
        ]);
        Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 300, 'due_date' => now()->subDays(5),
        ]);

        $alerts = $this->dashboard()->alerts();

        $this->assertSame(2, $alerts['overdueAccounts']);
        $this->assertSame('1300.00', $alerts['overdueAmount']);
    }

    public function test_alerts_surface_the_oldest_unpaid_invoice(): void
    {
        Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 100,
            'invoice_number' => 'INV-OLDEST', 'due_date' => now()->subDays(90),
        ]);
        Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 100, 'due_date' => now()->subDays(5),
        ]);

        $this->assertSame('INV-OLDEST', $this->dashboard()->alerts()['oldestUnpaid']->invoice_number);
    }

    public function test_customers_needing_attention_are_ranked_by_balance(): void
    {
        $small = Customer::factory()->create(['last_name' => 'Small']);
        $big = Customer::factory()->create(['last_name' => 'Big']);

        Invoice::factory()->for($small)->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 200, 'due_date' => now()->subDays(10),
        ]);
        Invoice::factory()->for($big)->count(2)->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 900, 'due_date' => now()->subDays(10),
        ]);

        $top = $this->dashboard()->alerts()['needingAttention']->first();

        $this->assertSame('Big', $top->last_name);
        $this->assertSame('1800.00', (string) $top->balance);
    }

    public function test_there_are_no_alerts_when_nothing_is_overdue(): void
    {
        Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 500, 'due_date' => now()->addWeek(),
        ]);

        $alerts = $this->dashboard()->alerts();

        $this->assertSame(0, $alerts['overdueAccounts']);
        $this->assertNull($alerts['oldestUnpaid']);
    }

    // -----------------------------------------------------------------
    // Charts
    // -----------------------------------------------------------------

    public function test_the_revenue_trend_always_spans_twelve_months(): void
    {
        Payment::factory()->ofAmount(1000)->create(['payment_date' => now()]);

        $trend = $this->dashboard()->revenueTrend();

        $this->assertCount(12, $trend['labels']);
        $this->assertCount(12, $trend['revenue']);
        $this->assertCount(12, $trend['payments']);
    }

    public function test_months_with_no_activity_are_filled_with_zero(): void
    {
        // Only the current month has a payment; the other eleven must still be
        // present as zeros so the axis is not compressed.
        Payment::factory()->ofAmount(2500)->create(['payment_date' => now()]);

        $trend = $this->dashboard()->revenueTrend();

        $this->assertSame('2500.00', $trend['revenue'][11]);
        $this->assertSame('0.00', $trend['revenue'][0]);
        $this->assertSame(0, $trend['payments'][0]);
    }

    public function test_the_customer_trend_counts_sign_ups_by_month(): void
    {
        Customer::factory()->count(3)->create(['created_at' => now()]);

        $trend = $this->dashboard()->customerTrend();

        $this->assertCount(12, $trend['labels']);
        $this->assertSame(3, $trend['customers'][11]);
    }

    public function test_the_mix_charts_omit_states_with_no_records(): void
    {
        Subscription::factory()->count(2)->create(['status' => SubscriptionStatus::Active]);
        Subscription::factory()->suspended()->create();

        $mix = $this->dashboard()->serviceMix();

        // Only the two states in use, not all five.
        $this->assertSame(['Active', 'Suspended'], $mix['labels']);
        $this->assertSame([2, 1], $mix['values']);
        $this->assertCount(2, $mix['colours']);
    }

    // -----------------------------------------------------------------
    // Recent activity
    // -----------------------------------------------------------------

    public function test_recent_lists_are_capped_and_newest_first(): void
    {
        Payment::factory()->count(8)->create(['payment_date' => now()->subDays(10)]);
        $newest = Payment::factory()->create(['payment_date' => now()]);

        $recent = $this->dashboard()->recentPayments();

        $this->assertCount(5, $recent);
        $this->assertTrue($recent->first()->is($newest));
    }

    // -----------------------------------------------------------------
    // The page, and who sees what
    // -----------------------------------------------------------------

    public function test_the_dashboard_renders_for_every_role(): void
    {
        Customer::factory()->count(2)->create();
        Subscription::factory()->create(['status' => SubscriptionStatus::Active]);
        Invoice::factory()->create(['status' => InvoiceStatus::Unpaid, 'balance_due' => 500]);
        Payment::factory()->create(['payment_date' => now()]);
        Expense::factory()->create(['expense_category_id' => ExpenseCategory::first()->id]);

        foreach ([Role::SUPER_ADMIN, Role::ADMINISTRATOR, Role::BILLING_STAFF,
            Role::TECHNICIAN, Role::ACCOUNTANT] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('dashboard'))->assertOk();
        }
    }

    public function test_a_technician_gets_no_financial_panels(): void
    {
        Payment::factory()->ofAmount(4242)->create(['payment_date' => now()]);

        $response = $this->actingAs($this->userWithRole(Role::TECHNICIAN))->get(route('dashboard'));

        $response->assertOk();
        // Customers and services, but nothing about money.
        $response->assertViewHas('customerStats');
        $response->assertViewHas('serviceStats');
        $response->assertViewMissing('financialStats');
        $response->assertViewMissing('billingStats');
        $response->assertViewMissing('recentPayments');
        $response->assertDontSee('4,242.00');
    }

    public function test_billing_staff_get_billing_panels_but_not_the_finance_block(): void
    {
        $response = $this->actingAs($this->userWithRole(Role::BILLING_STAFF))->get(route('dashboard'));

        $response->assertViewHas('billingStats');
        $response->assertViewHas('recentPayments');
        // revenue/expenses/net needs reports.financial, which billing staff lack.
        $response->assertViewMissing('financialStats');
    }

    public function test_an_accountant_gets_the_finance_block(): void
    {
        $response = $this->actingAs($this->userWithRole(Role::ACCOUNTANT))->get(route('dashboard'));

        $response->assertViewHas('financialStats');
        $response->assertViewHas('billingStats');
        // Accountants hold no subscriptions.view.
        $response->assertViewMissing('serviceStats');
    }

    public function test_a_user_with_no_role_sees_an_empty_dashboard_rather_than_an_error(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('does not include access to any dashboard statistics');
    }

    public function test_the_overdue_alert_appears_only_when_something_is_overdue(): void
    {
        $staff = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($staff)->get(route('dashboard'))->assertDontSee('account overdue');

        Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 750, 'due_date' => now()->subDays(15),
        ]);

        $this->actingAs($staff)->get(route('dashboard'))->assertSee('750.00');
    }
}
