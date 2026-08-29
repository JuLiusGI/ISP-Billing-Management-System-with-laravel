<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerContact;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InternetPlan;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Permission;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\ServiceStatusLog;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_reaches_its_addresses_contacts_and_services(): void
    {
        $customer = Customer::factory()->create();

        CustomerAddress::factory()->for($customer)->create();
        CustomerContact::factory()->for($customer)->count(2)->create();
        $subscription = Subscription::factory()->for($customer)->create();
        Invoice::factory()->for($customer)->count(3)->create();
        Payment::factory()->for($customer)->create();
        ServiceStatusLog::factory()->forSubscription($subscription)->create();

        $customer->refresh();

        $this->assertCount(1, $customer->addresses);
        $this->assertCount(2, $customer->contacts);
        $this->assertCount(1, $customer->subscriptions);
        $this->assertCount(3, $customer->invoices);
        $this->assertCount(1, $customer->payments);
        $this->assertCount(1, $customer->serviceStatusLogs);
        $this->assertTrue($customer->primaryAddress->is_primary);
    }

    public function test_a_subscription_belongs_to_a_customer_and_a_plan(): void
    {
        $plan = InternetPlan::factory()->create();
        $customer = Customer::factory()->create();
        $subscription = Subscription::factory()->for($customer)->forPlan($plan)->create();

        $this->assertTrue($subscription->customer->is($customer));
        $this->assertTrue($subscription->internetPlan->is($plan));
        $this->assertCount(1, $plan->subscriptions);
    }

    public function test_an_invoice_reaches_its_items_allocations_and_payments(): void
    {
        $invoice = Invoice::factory()->create();
        $payment = Payment::factory()->for($invoice->customer)->create();

        PaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => 500,
        ]);

        $invoice->refresh();

        $this->assertCount(1, $invoice->items);
        $this->assertCount(1, $invoice->allocations);
        $this->assertCount(1, $invoice->payments);
        $this->assertTrue($invoice->payments->first()->is($payment));
    }

    public function test_a_payment_reaches_its_allocations_and_receipt(): void
    {
        $payment = Payment::factory()->create();
        $invoice = Invoice::factory()->for($payment->customer)->create();

        PaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => 250,
        ]);
        Receipt::factory()->for($payment)->create();

        $payment->refresh();

        $this->assertCount(1, $payment->allocations);
        $this->assertNotNull($payment->receipt);
        $this->assertTrue($payment->allocations->first()->invoice->is($invoice));
    }

    public function test_one_payment_can_settle_several_invoices(): void
    {
        $customer = Customer::factory()->create();
        $payment = Payment::factory()->for($customer)->ofAmount(3000)->create();
        $invoices = Invoice::factory()->for($customer)->count(3)->create();

        foreach ($invoices as $invoice) {
            PaymentAllocation::factory()->create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => 1000,
            ]);
        }

        $this->assertCount(3, $payment->refresh()->allocations);

        foreach ($invoices as $invoice) {
            $this->assertSame('1000.00', $invoice->allocatedTotal());
        }
    }

    public function test_a_user_reaches_permissions_through_roles(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create(['name' => 'billing-staff']);
        $permission = Permission::factory()->create(['name' => 'invoices.create']);

        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $user->refresh();

        $this->assertCount(1, $user->roles);
        $this->assertTrue($user->hasRole('billing-staff'));
        $this->assertTrue($user->hasPermission('invoices.create'));
        $this->assertFalse($user->hasPermission('users.delete'));
    }

    public function test_a_super_admin_holds_every_ability_implicitly(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => Role::SUPER_ADMIN]));

        $this->assertTrue($user->refresh()->hasPermission('anything.at.all'));
    }

    public function test_an_expense_belongs_to_its_category(): void
    {
        $category = ExpenseCategory::factory()->create();
        $expense = Expense::factory()->for($category, 'category')->create();

        $this->assertTrue($expense->category->is($category));
        $this->assertCount(1, $category->expenses);
    }
}
