<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\FinancialReportService;
use App\Services\OperationalReportService;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingTest extends TestCase
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

    private function financial(): FinancialReportService
    {
        return app(FinancialReportService::class);
    }

    // -----------------------------------------------------------------
    // Revenue
    // -----------------------------------------------------------------

    public function test_revenue_counts_only_completed_payments(): void
    {
        $customer = Customer::factory()->create();

        Payment::factory()->for($customer)->ofAmount(1000)->create(['payment_date' => now()->subDays(5)]);
        Payment::factory()->for($customer)->ofAmount(500)->create(['payment_date' => now()->subDays(3)]);
        // Reversed money is not money received.
        Payment::factory()->for($customer)->ofAmount(9999)->reversed()->create(['payment_date' => now()->subDays(4)]);

        $report = $this->financial()->revenue(now()->subMonth(), now());

        $this->assertSame('1500.00', $report['total']);
        $this->assertSame(2, $report['count']);
        $this->assertSame('750.00', $report['average']);
    }

    public function test_revenue_respects_the_date_range(): void
    {
        Payment::factory()->ofAmount(1000)->create(['payment_date' => now()->subDays(2)]);
        Payment::factory()->ofAmount(2000)->create(['payment_date' => now()->subMonths(8)]);

        $report = $this->financial()->revenue(now()->subMonth(), now());

        $this->assertSame('1000.00', $report['total']);
    }

    public function test_revenue_can_be_filtered_by_payment_method(): void
    {
        Payment::factory()->ofAmount(1000)->method(PaymentMethod::Cash)->create(['payment_date' => now()]);
        Payment::factory()->ofAmount(700)->method(PaymentMethod::Gcash)->create(['payment_date' => now()]);

        $this->assertSame(
            '700.00',
            $this->financial()->revenue(now()->subMonth(), now(), PaymentMethod::Gcash->value)['total']
        );
    }

    public function test_revenue_groups_by_method(): void
    {
        Payment::factory()->count(2)->ofAmount(500)->method(PaymentMethod::Cash)->create(['payment_date' => now()]);
        Payment::factory()->ofAmount(300)->method(PaymentMethod::Gcash)->create(['payment_date' => now()]);

        $byMethod = $this->financial()->revenue(now()->subMonth(), now())['byMethod'];

        $this->assertCount(2, $byMethod);
        $this->assertSame('1000.00', (string) $byMethod->first()->total);
        $this->assertSame(2, (int) $byMethod->first()->entries);
    }

    public function test_an_empty_period_reports_zero_rather_than_failing(): void
    {
        $report = $this->financial()->revenue(now()->subYear(), now()->subMonths(11));

        $this->assertSame('0.00', $report['average']);
        $this->assertSame(0, $report['count']);
        $this->assertTrue($report['byMethod']->isEmpty());
    }

    // -----------------------------------------------------------------
    // Billing, outstanding and overdue
    // -----------------------------------------------------------------

    public function test_billing_excludes_cancelled_invoices_from_the_billed_total(): void
    {
        Invoice::factory()->count(2)->create([
            'invoice_date' => now()->subDays(3), 'total_amount' => 1000,
            'balance_due' => 1000, 'amount_paid' => 0, 'status' => InvoiceStatus::Unpaid,
        ]);
        Invoice::factory()->cancelled()->create([
            'invoice_date' => now()->subDays(3), 'total_amount' => 5000, 'balance_due' => 0,
        ]);

        $report = $this->financial()->billing(now()->subMonth(), now());

        $this->assertSame('2000.00', $report['invoiced']);
        $this->assertSame('2000.00', $report['outstanding']);
        // The cancelled one is still counted as an issued document.
        $this->assertSame(3, $report['count']);
    }

    public function test_outstanding_ages_invoices_into_buckets(): void
    {
        $this->unpaidDueDaysAgo(10, 100);   // 1-30
        $this->unpaidDueDaysAgo(45, 200);   // 31-60
        $this->unpaidDueDaysAgo(120, 400);  // over 90
        $this->unpaidDueInDays(10, 800);    // not yet due

        $report = $this->financial()->outstanding();

        $this->assertSame('100.00', $report['buckets']['1-30 days']['total']);
        $this->assertSame('200.00', $report['buckets']['31-60 days']['total']);
        $this->assertSame('400.00', $report['buckets']['Over 90 days']['total']);
        $this->assertSame('800.00', $report['buckets']['Not yet due']['total']);
        $this->assertSame('1500.00', $report['total']);
    }

    public function test_outstanding_ignores_paid_and_cancelled_invoices(): void
    {
        $this->unpaidDueDaysAgo(10, 500);
        Invoice::factory()->create([
            'status' => InvoiceStatus::Paid, 'balance_due' => 0,
            'due_date' => now()->subDays(10),
        ]);
        Invoice::factory()->cancelled()->create([
            'balance_due' => 9999, 'due_date' => now()->subDays(10),
        ]);

        $this->assertSame('500.00', $this->financial()->outstanding()['total']);
    }

    public function test_outstanding_lists_the_largest_debtors(): void
    {
        $big = Customer::factory()->create(['last_name' => 'Owesalot']);
        Invoice::factory()->for($big)->count(2)->create([
            'status' => InvoiceStatus::Unpaid, 'balance_due' => 1000, 'due_date' => now()->subDays(5),
        ]);
        $this->unpaidDueDaysAgo(5, 100);

        $top = $this->financial()->outstanding()['topDebtors']->first();

        $this->assertSame('Owesalot', $top->last_name);
        $this->assertSame('2000.00', (string) $top->balance);
        $this->assertSame(2, (int) $top->invoices);
    }

    public function test_overdue_excludes_invoices_not_yet_due(): void
    {
        $this->unpaidDueDaysAgo(5, 300);
        $this->unpaidDueInDays(5, 700);

        $report = $this->financial()->overdue();

        $this->assertSame('300.00', $report['total']);
        $this->assertSame(1, $report['count']);
    }

    // -----------------------------------------------------------------
    // Expenses and the summary
    // -----------------------------------------------------------------

    public function test_expenses_exclude_archived_entries(): void
    {
        $category = ExpenseCategory::first();

        Expense::factory()->count(2)->create([
            'expense_category_id' => $category->id, 'amount' => 250, 'expense_date' => now()->subDays(2),
        ]);
        Expense::factory()->create([
            'expense_category_id' => $category->id, 'amount' => 9999, 'expense_date' => now()->subDays(2),
        ])->delete();

        $report = $this->financial()->expenses(now()->subMonth(), now());

        $this->assertSame('500.00', $report['total']);
        $this->assertSame('500.00', (string) $report['byCategory']->first()->total);
    }

    public function test_the_summary_is_gross_revenue_less_expenses(): void
    {
        Payment::factory()->ofAmount(10000)->create(['payment_date' => now()->subDays(5)]);
        Expense::factory()->create([
            'expense_category_id' => ExpenseCategory::first()->id,
            'amount' => 2500.50,
            'expense_date' => now()->subDays(4),
        ]);

        $report = $this->financial()->summary(now()->subMonth(), now());

        $this->assertSame('10000.00', $report['grossRevenue']);
        $this->assertSame('2500.50', $report['expenses']);
        $this->assertSame('7499.50', $report['net']);
    }

    public function test_the_summary_reports_a_loss_as_a_negative_net(): void
    {
        Payment::factory()->ofAmount(1000)->create(['payment_date' => now()->subDays(2)]);
        Expense::factory()->create([
            'expense_category_id' => ExpenseCategory::first()->id,
            'amount' => 4000, 'expense_date' => now()->subDays(2),
        ]);

        $report = $this->financial()->summary(now()->subMonth(), now());

        $this->assertSame('-3000.00', $report['net']);
    }

    public function test_the_summary_breaks_the_period_down_by_month(): void
    {
        Payment::factory()->ofAmount(1000)->create(['payment_date' => now()->subMonths(2)->startOfMonth()->addDay()]);
        Payment::factory()->ofAmount(2000)->create(['payment_date' => now()->startOfMonth()->addDay()]);
        Expense::factory()->create([
            'expense_category_id' => ExpenseCategory::first()->id,
            'amount' => 500, 'expense_date' => now()->startOfMonth()->addDay(),
        ]);

        $months = $this->financial()->summary(now()->subMonths(3), now())['months'];

        $this->assertGreaterThanOrEqual(2, $months->count());
        $this->assertSame('1500.00', $months->last()->net);
    }

    public function test_a_summary_with_no_revenue_does_not_divide_by_zero(): void
    {
        $report = $this->financial()->summary(now()->subYear(), now()->subMonths(11));

        $this->assertSame('0.00', $report['margin']);
        $this->assertSame('0.00', $report['net']);
    }

    // -----------------------------------------------------------------
    // Operational
    // -----------------------------------------------------------------

    public function test_the_customer_report_counts_the_base_and_new_sign_ups(): void
    {
        Customer::factory()->count(4)->create(['created_at' => now()->subDays(3)]);
        Customer::factory()->count(2)->suspended()->create(['created_at' => now()->subYear()]);

        $report = app(OperationalReportService::class)->customers(now()->subMonth(), now());

        $this->assertSame(6, $report['total']);
        $this->assertSame(4, $report['newInPeriod']);
        $this->assertSame(4, $report['activeShare']);
    }

    public function test_the_service_report_totals_recurring_revenue_for_active_lines_only(): void
    {
        Subscription::factory()->count(2)->create([
            'status' => SubscriptionStatus::Active, 'monthly_rate' => 1500, 'discount_amount' => 0,
        ]);
        Subscription::factory()->create([
            'status' => SubscriptionStatus::Active, 'monthly_rate' => 1000, 'discount_amount' => 200,
        ]);
        Subscription::factory()->suspended()->create(['monthly_rate' => 9999, 'discount_amount' => 0]);

        $report = app(OperationalReportService::class)->services(now()->subMonth(), now());

        // 1500 + 1500 + (1000 - 200), with the suspended line excluded.
        $this->assertSame('3800.00', $report['monthlyRecurring']);
        $this->assertSame(4, $report['total']);
    }

    // -----------------------------------------------------------------
    // Screens, filters and export
    // -----------------------------------------------------------------

    public function test_every_report_screen_renders(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);

        Payment::factory()->count(2)->create(['payment_date' => now()->subDays(2)]);
        Expense::factory()->create(['expense_category_id' => ExpenseCategory::first()->id]);
        $this->unpaidDueDaysAgo(10, 400);
        Subscription::factory()->create(['status' => SubscriptionStatus::Active]);

        foreach ([
            'reports.index', 'reports.summary', 'reports.revenue', 'reports.expenses',
            'reports.payments', 'reports.billing', 'reports.outstanding', 'reports.overdue',
            'reports.customers', 'reports.services',
        ] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_a_reversed_date_range_is_swapped_rather_than_rejected(): void
    {
        Payment::factory()->ofAmount(1000)->create(['payment_date' => now()->subDays(5)]);

        // "from" after "to" should still produce the report, not an error.
        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->get(route('reports.revenue', [
                'from' => now()->toDateString(),
                'to' => now()->subMonth()->toDateString(),
            ]))
            ->assertOk()
            ->assertViewHas('report', fn ($report) => $report['total'] === '1000.00');
    }

    public function test_an_unparseable_date_falls_back_to_the_default_range(): void
    {
        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->get(route('reports.revenue', ['from' => 'not-a-date']))
            ->assertOk();
    }

    public function test_a_report_exports_as_csv(): void
    {
        Payment::factory()->ofAmount(1250)->create(['payment_date' => now()->subDays(2)]);

        $response = $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->get(route('reports.revenue', ['export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $response->streamedContent();

        $this->assertStringContainsString('Period,Payments,Total', $body);
        $this->assertStringContainsString('1250.00', $body);
    }

    public function test_the_payment_export_lists_individual_payments(): void
    {
        $customer = Customer::factory()->create(['last_name' => 'Villanueva']);
        Payment::factory()->for($customer)->ofAmount(999)->create(['payment_date' => now()->subDay()]);

        $body = $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->get(route('reports.payments', ['export' => 'csv']))
            ->streamedContent();

        $this->assertStringContainsString('Villanueva', $body);
        $this->assertStringContainsString('999.00', $body);
    }

    public function test_the_payment_report_hides_reversed_payments_unless_asked(): void
    {
        Payment::factory()->ofAmount(1000)->create(['payment_date' => now()->subDay()]);
        Payment::factory()->ofAmount(500)->reversed()->create(['payment_date' => now()->subDay()]);

        $accountant = $this->userWithRole(Role::ACCOUNTANT);

        $this->actingAs($accountant)->get(route('reports.payments'))
            ->assertViewHas('total', '1000.00');

        $this->actingAs($accountant)->get(route('reports.payments', ['status' => 'reversed']))
            ->assertViewHas('total', '500.00');
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_each_role_reaches_only_the_reports_over_data_it_can_already_read(): void
    {
        $matrix = [
            Role::BILLING_STAFF => [
                'allowed' => ['reports.index', 'reports.payments', 'reports.billing', 'reports.outstanding', 'reports.overdue'],
                'denied' => ['reports.summary', 'reports.revenue', 'reports.expenses', 'reports.customers', 'reports.services'],
            ],
            Role::TECHNICIAN => [
                'allowed' => ['reports.index', 'reports.customers', 'reports.services'],
                'denied' => ['reports.summary', 'reports.revenue', 'reports.expenses', 'reports.payments', 'reports.billing'],
            ],
            Role::ACCOUNTANT => [
                'allowed' => ['reports.index', 'reports.summary', 'reports.revenue', 'reports.expenses', 'reports.payments', 'reports.billing'],
                'denied' => ['reports.customers', 'reports.services'],
            ],
        ];

        foreach ($matrix as $role => $expectations) {
            $user = $this->userWithRole($role);

            foreach ($expectations['allowed'] as $route) {
                $this->actingAs($user)->get(route($route))->assertOk();
            }

            foreach ($expectations['denied'] as $route) {
                $this->actingAs($user)->get(route($route))->assertForbidden();
            }
        }
    }

    public function test_the_trend_grouping_refuses_a_column_it_does_not_recognise(): void
    {
        /*
         * The column names are interpolated into raw SQL. No call site passes
         * user input today, but a private helper that would inject if one ever
         * did is worth closing rather than documenting.
         */
        $method = new \ReflectionMethod(FinancialReportService::class, 'overTime');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);

        $method->invoke(
            $this->financial(),
            Payment::query(),
            "payment_date'); DROP TABLE payments; --",
            'amount',
            now()->subMonth(),
            now(),
        );
    }

    public function test_a_user_with_no_role_reaches_no_reports(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_the_hub_only_lists_reports_the_user_may_open(): void
    {
        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Customer Report')
            ->assertDontSee('Financial Summary')
            ->assertDontSee('Expense Report');
    }

    // -----------------------------------------------------------------

    private function unpaidDueDaysAgo(int $days, float $balance): Invoice
    {
        return Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid,
            'total_amount' => $balance,
            'balance_due' => $balance,
            'due_date' => now()->subDays($days),
            'invoice_date' => now()->subDays($days + 15),
        ]);
    }

    private function unpaidDueInDays(int $days, float $balance): Invoice
    {
        return Invoice::factory()->create([
            'status' => InvoiceStatus::Unpaid,
            'total_amount' => $balance,
            'balance_due' => $balance,
            'due_date' => now()->addDays($days),
            'invoice_date' => now(),
        ]);
    }
}
