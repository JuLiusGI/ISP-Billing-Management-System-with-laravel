<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SettingType;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Subscription;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelBehaviourTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_is_given_an_account_number_automatically(): void
    {
        $customer = Customer::factory()->create();

        $this->assertNotNull($customer->account_number);
        $this->assertMatchesRegularExpression('/^ACC-\d{4}-\d{5}$/', $customer->account_number);
    }

    public function test_account_numbers_do_not_repeat(): void
    {
        $numbers = Customer::factory()->count(15)->create()->pluck('account_number');

        $this->assertCount(15, $numbers->unique());
    }

    public function test_a_subscription_is_given_a_code_automatically(): void
    {
        $this->assertMatchesRegularExpression(
            '/^SUB-\d{4}-\d{5}$/',
            Subscription::factory()->create()->subscription_code
        );
    }

    public function test_repricing_a_plan_leaves_existing_subscriptions_untouched(): void
    {
        $plan = InternetPlan::factory()->priced(1500)->create();
        $subscription = Subscription::factory()->forPlan($plan)->create();

        $this->assertSame('1500.00', $subscription->monthly_rate);

        $plan->update(['monthly_price' => 2500]);

        $this->assertSame('2500.00', $plan->refresh()->monthly_price);
        $this->assertSame('1500.00', $subscription->refresh()->monthly_rate);
    }

    public function test_repricing_a_plan_leaves_historical_invoices_untouched(): void
    {
        $plan = InternetPlan::factory()->priced(1500)->create();
        $subscription = Subscription::factory()->forPlan($plan)->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'subtotal' => 1500,
            'total_amount' => 1500,
            'balance_due' => 1500,
        ]);

        $plan->update(['monthly_price' => 9999]);

        $this->assertSame('1500.00', $invoice->refresh()->total_amount);
        $this->assertSame('1500.00', $invoice->items->first()->line_total);
    }

    public function test_money_is_cast_to_exact_decimal_strings_not_floats(): void
    {
        $plan = InternetPlan::factory()->priced(1499.99)->create();

        $this->assertIsString($plan->refresh()->monthly_price);
        $this->assertSame('1499.99', $plan->monthly_price);
    }

    public function test_status_columns_are_cast_to_enums(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create();

        $this->assertInstanceOf(CustomerStatus::class, $customer->status);
        $this->assertInstanceOf(InvoiceStatus::class, $invoice->status);
        $this->assertSame('Unpaid', $invoice->status->label());
    }

    public function test_an_invoice_balance_ignores_reversed_payments(): void
    {
        $invoice = Invoice::factory()->create([
            'subtotal' => 1000, 'total_amount' => 1000, 'balance_due' => 1000,
        ]);

        $good = Payment::factory()->for($invoice->customer)->ofAmount(400)->create();
        $reversed = Payment::factory()->for($invoice->customer)->ofAmount(600)->reversed()->create();

        foreach ([[$good, 400], [$reversed, 600]] as [$payment, $amount]) {
            PaymentAllocation::factory()->create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
            ]);
        }

        // Only the completed payment may count toward the balance.
        $this->assertSame('400.00', $invoice->allocatedTotal());
        $this->assertSame('600.00', $invoice->calculatedBalance());
    }

    public function test_an_overpaid_invoice_never_reports_a_negative_balance(): void
    {
        $invoice = Invoice::factory()->create([
            'subtotal' => 500, 'total_amount' => 500, 'balance_due' => 500,
        ]);

        $payment = Payment::factory()->for($invoice->customer)->ofAmount(800)->create();
        PaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => 800,
        ]);

        $this->assertSame('0.00', $invoice->calculatedBalance());
    }

    public function test_an_overpayment_is_held_as_unallocated_credit(): void
    {
        $payment = Payment::factory()->ofAmount(1000)->create(['allocated_amount' => 600]);

        $this->assertSame('400.00', $payment->unallocatedAmount());
        $this->assertFalse($payment->isFullyAllocated());
    }

    public function test_the_paid_factory_state_produces_a_real_settled_invoice(): void
    {
        $invoice = Invoice::factory()->paid()->create([
            'subtotal' => 1200, 'total_amount' => 1200, 'balance_due' => 1200,
        ]);

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertSame('1200.00', $invoice->allocatedTotal());
        $this->assertSame('1200.00', $invoice->amount_paid);
    }

    public function test_the_partially_paid_factory_state_leaves_a_real_balance(): void
    {
        $invoice = Invoice::factory()->partiallyPaid(500)->create([
            'subtotal' => 2000, 'total_amount' => 2000, 'balance_due' => 2000,
        ]);

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertSame('1500.00', $invoice->balance_due);
        $this->assertSame('1500.00', $invoice->calculatedBalance());
    }

    public function test_an_invoice_line_total_is_computed_without_float_error(): void
    {
        $item = new InvoiceItem([
            'quantity' => 3,
            'unit_price' => 1499.99,
            'discount_amount' => 0.97,
        ]);

        $this->assertSame('4499.00', $item->computeLineTotal());
    }

    public function test_overdue_detection_uses_the_due_date_and_status(): void
    {
        $overdue = Invoice::factory()->overdue(20)->create();
        $paid = Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'balance_due' => 0]);

        $this->assertTrue($overdue->isOverdue());
        $this->assertFalse($paid->isOverdue());
        $this->assertGreaterThanOrEqual(19, $overdue->daysOverdue());
    }

    public function test_the_overdue_scope_only_returns_unsettled_invoices(): void
    {
        Invoice::factory()->overdue()->count(2)->create();
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'due_date' => now()->subDays(60)]);
        Invoice::factory()->cancelled()->create(['due_date' => now()->subDays(60)]);

        $this->assertSame(2, Invoice::overdue()->count());
    }

    public function test_completed_scope_excludes_reversed_payments(): void
    {
        Payment::factory()->count(3)->create();
        Payment::factory()->reversed()->count(2)->create();

        $this->assertSame(3, Payment::completed()->count());
        $this->assertSame(5, Payment::count());
    }

    public function test_customer_search_matches_account_number_and_name(): void
    {
        $target = Customer::factory()->create(['last_name' => 'Villanueva']);
        Customer::factory()->count(4)->create(['last_name' => 'Reyes']);

        $this->assertSame(1, Customer::search('Villanueva')->count());
        $this->assertSame(1, Customer::search($target->account_number)->count());
        $this->assertSame(5, Customer::search(null)->count());
    }

    public function test_soft_deleted_records_leave_financial_history_reachable(): void
    {
        $customer = Customer::factory()->create();
        Invoice::factory()->for($customer)->create();

        $customer->delete();

        $this->assertSoftDeleted($customer);
        $this->assertSame(0, Customer::count());
        $this->assertSame(1, Customer::withTrashed()->count());
        $this->assertSame(1, Invoice::count());
    }

    public function test_settings_are_returned_in_their_declared_type(): void
    {
        $boolean = SystemSetting::factory()->ofType(SettingType::Boolean, '1')->create();
        $integer = SystemSetting::factory()->ofType(SettingType::Integer, '15')->create();
        $json = SystemSetting::factory()->ofType(SettingType::Json, '{"a":1}')->create();

        $this->assertTrue($boolean->typed_value);
        $this->assertSame(15, $integer->typed_value);
        $this->assertSame(['a' => 1], $json->typed_value);
    }
}
