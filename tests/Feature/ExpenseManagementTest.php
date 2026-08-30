<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\ExpenseService;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
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

    private function activeCategory(): ExpenseCategory
    {
        return ExpenseCategory::where('is_active', true)->first();
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'expense_category_id' => $this->activeCategory()->id,
            'description' => 'Upstream bandwidth for August',
            'amount' => '52500.00',
            'expense_date' => now()->subDays(3)->toDateString(),
            'payment_method' => PaymentMethod::BankTransfer->value,
            'vendor' => 'Regional Backbone Inc.',
            'notes' => null,
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Recording
    // -----------------------------------------------------------------

    public function test_an_accountant_can_record_an_expense(): void
    {
        $accountant = $this->userWithRole(Role::ACCOUNTANT);

        $this->actingAs($accountant)
            ->post(route('expenses.store'), $this->validPayload())
            ->assertRedirect();

        $expense = Expense::first();

        $this->assertNotNull($expense);
        $this->assertSame('52500.00', $expense->amount);
        $this->assertSame($accountant->id, $expense->created_by);
        $this->assertSame(PaymentMethod::BankTransfer, $expense->payment_method);
    }

    public function test_the_reference_is_generated_and_never_taken_from_input(): void
    {
        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->post(route('expenses.store'), $this->validPayload([
                'expense_reference' => 'ATTACKER-SUPPLIED',
            ]));

        $this->assertDatabaseMissing('expenses', ['expense_reference' => 'ATTACKER-SUPPLIED']);
        $this->assertMatchesRegularExpression('/^EXP-\d{4}-\d{6}$/', Expense::first()->expense_reference);
    }

    public function test_references_do_not_repeat(): void
    {
        $service = app(ExpenseService::class);
        $category = $this->activeCategory();

        $references = collect(range(1, 10))->map(fn () => $service->record([
            'expense_category_id' => $category->id,
            'description' => 'Repeated entry',
            'amount' => '100.00',
            'expense_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::Cash->value,
        ])->expense_reference);

        $this->assertCount(10, $references->unique());
    }

    public function test_an_expense_must_be_for_more_than_zero(): void
    {
        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->post(route('expenses.store'), $this->validPayload(['amount' => '0']))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Expense::count());
    }

    public function test_an_expense_cannot_be_dated_in_the_future(): void
    {
        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->post(route('expenses.store'), $this->validPayload([
                'expense_date' => now()->addWeek()->toDateString(),
            ]))
            ->assertSessionHasErrors('expense_date');
    }

    public function test_required_fields_are_enforced(): void
    {
        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->post(route('expenses.store'), [])
            ->assertSessionHasErrors([
                'expense_category_id', 'description', 'amount', 'expense_date', 'payment_method',
            ]);
    }

    public function test_a_retired_category_cannot_be_chosen_for_a_new_expense(): void
    {
        $retired = ExpenseCategory::create([
            'name' => 'Retired Line', 'code' => 'RETIRED', 'is_active' => false,
        ]);

        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->post(route('expenses.store'), $this->validPayload(['expense_category_id' => $retired->id]))
            ->assertSessionHasErrors('expense_category_id');
    }

    // -----------------------------------------------------------------
    // Editing
    // -----------------------------------------------------------------

    public function test_an_expense_can_be_edited(): void
    {
        $expense = Expense::factory()->create(['description' => 'Original']);

        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->put(route('expenses.update', $expense), $this->validPayload([
                'description' => 'Corrected description',
                'amount' => '999.50',
            ]))
            ->assertRedirect();

        $expense->refresh();

        $this->assertSame('Corrected description', $expense->description);
        $this->assertSame('999.50', $expense->amount);
    }

    public function test_the_reference_survives_an_edit(): void
    {
        $expense = Expense::factory()->create();
        $original = $expense->expense_reference;

        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->put(route('expenses.update', $expense), $this->validPayload([
                'expense_reference' => 'CHANGED',
            ]));

        $this->assertSame($original, $expense->fresh()->expense_reference);
    }

    public function test_an_expense_already_filed_under_a_retired_category_stays_editable(): void
    {
        $retired = ExpenseCategory::create([
            'name' => 'Retired Line', 'code' => 'RETIRED', 'is_active' => false,
        ]);
        $expense = Expense::factory()->create(['expense_category_id' => $retired->id]);

        // Keeping the existing category must not be blocked by it being retired.
        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->put(route('expenses.update', $expense), $this->validPayload([
                'expense_category_id' => $retired->id,
                'description' => 'Still editable',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Still editable', $expense->fresh()->description);
    }

    public function test_moving_an_expense_to_a_different_retired_category_is_refused(): void
    {
        $current = ExpenseCategory::create(['name' => 'A', 'code' => 'A', 'is_active' => false]);
        $other = ExpenseCategory::create(['name' => 'B', 'code' => 'B', 'is_active' => false]);
        $expense = Expense::factory()->create(['expense_category_id' => $current->id]);

        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->put(route('expenses.update', $expense), $this->validPayload([
                'expense_category_id' => $other->id,
            ]))
            ->assertSessionHasErrors('expense_category_id');
    }

    // -----------------------------------------------------------------
    // Filtering and the summary
    // -----------------------------------------------------------------

    public function test_the_listing_can_be_filtered_by_category_and_date(): void
    {
        $a = $this->activeCategory();
        $b = ExpenseCategory::where('is_active', true)->where('id', '!=', $a->id)->first();

        Expense::factory()->count(2)->create([
            'expense_category_id' => $a->id, 'expense_date' => now()->subDays(2),
        ]);
        Expense::factory()->count(3)->create([
            'expense_category_id' => $b->id, 'expense_date' => now()->subMonths(6),
        ]);

        $accountant = $this->userWithRole(Role::ACCOUNTANT);

        $this->actingAs($accountant)->get(route('expenses.index', ['category' => $a->id]))
            ->assertOk()->assertViewHas('expenses', fn ($e) => $e->total() === 2);

        $this->actingAs($accountant)
            ->get(route('expenses.index', ['from' => now()->subWeek()->toDateString()]))
            ->assertOk()->assertViewHas('expenses', fn ($e) => $e->total() === 2);
    }

    public function test_the_summary_totals_the_whole_filtered_set_not_just_the_page(): void
    {
        // 20 rows at 100.00 each, over a 15-per-page listing.
        Expense::factory()->count(20)->create([
            'amount' => '100.00',
            'expense_category_id' => $this->activeCategory()->id,
            'expense_date' => now()->subDay(),
        ]);

        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertViewHas('expenses', fn ($e) => $e->count() === 15 && $e->total() === 20)
            ->assertViewHas('total', fn ($total) => (float) $total === 2000.0);
    }

    public function test_the_summary_breaks_spend_down_by_category(): void
    {
        $a = $this->activeCategory();
        $b = ExpenseCategory::where('is_active', true)->where('id', '!=', $a->id)->first();

        Expense::factory()->count(2)->create(['expense_category_id' => $a->id, 'amount' => '500.00']);
        Expense::factory()->create(['expense_category_id' => $b->id, 'amount' => '250.00']);

        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->get(route('expenses.index'))
            ->assertViewHas('byCategory', function ($rows) {
                // Ordered largest first.
                return $rows->count() === 2
                    && (float) $rows->first()->total === 1000.0
                    && (int) $rows->first()->entries === 2;
            });
    }

    public function test_archived_expenses_are_excluded_from_the_totals(): void
    {
        $category = $this->activeCategory();
        Expense::factory()->count(2)->create(['expense_category_id' => $category->id, 'amount' => '100.00']);
        Expense::factory()->create(['expense_category_id' => $category->id, 'amount' => '999.00'])->delete();

        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->get(route('expenses.index'))
            ->assertViewHas('total', fn ($total) => (float) $total === 200.0)
            ->assertViewHas('byCategory', fn ($rows) => (float) $rows->first()->total === 200.0);
    }

    // -----------------------------------------------------------------
    // Archiving
    // -----------------------------------------------------------------

    public function test_an_expense_can_be_archived_and_restored(): void
    {
        $accountant = $this->userWithRole(Role::ACCOUNTANT);
        $expense = Expense::factory()->create();

        $this->actingAs($accountant)->delete(route('expenses.destroy', $expense))
            ->assertRedirect(route('expenses.index'));
        $this->assertSoftDeleted($expense);

        $this->actingAs($accountant)->post(route('expenses.restore', $expense->id))->assertRedirect();
        $this->assertNotSoftDeleted($expense);
    }

    public function test_archived_expenses_are_hidden_unless_asked_for(): void
    {
        Expense::factory()->count(2)->create();
        Expense::factory()->create()->delete();

        $accountant = $this->userWithRole(Role::ACCOUNTANT);

        $this->actingAs($accountant)->get(route('expenses.index'))
            ->assertViewHas('expenses', fn ($e) => $e->total() === 2);

        $this->actingAs($accountant)->get(route('expenses.index', ['archived' => 1]))
            ->assertViewHas('expenses', fn ($e) => $e->total() === 1);
    }

    // -----------------------------------------------------------------
    // Categories
    // -----------------------------------------------------------------

    public function test_a_category_can_be_added_with_a_derived_code(): void
    {
        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->post(route('expense-categories.store'), [
                'name' => 'Tower Rental',
                'description' => 'Site lease payments.',
            ])->assertRedirect(route('expense-categories.index'));

        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Tower Rental',
            'code' => 'TOWER_RENTAL',
            'is_active' => true,
        ]);
    }

    public function test_duplicate_category_names_are_rejected(): void
    {
        $existing = $this->activeCategory();

        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->post(route('expense-categories.store'), ['name' => $existing->name])
            ->assertSessionHasErrors('name');
    }

    public function test_a_category_can_be_retired_without_touching_its_expenses(): void
    {
        $category = $this->activeCategory();
        Expense::factory()->count(2)->create(['expense_category_id' => $category->id]);

        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->put(route('expense-categories.update', $category), [
                'name' => $category->name,
                'is_active' => 0,
            ])->assertRedirect();

        $this->assertFalse($category->fresh()->is_active);
        $this->assertSame(2, Expense::where('expense_category_id', $category->id)->count());
    }

    public function test_a_category_in_use_cannot_be_deleted(): void
    {
        $category = $this->activeCategory();
        Expense::factory()->create(['expense_category_id' => $category->id]);

        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->delete(route('expense-categories.destroy', $category))
            ->assertForbidden();

        $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);
    }

    public function test_an_unused_category_can_be_deleted(): void
    {
        $category = ExpenseCategory::create(['name' => 'Unused', 'code' => 'UNUSED', 'is_active' => true]);

        $this->actingAs($this->userWithRole(Role::ACCOUNTANT))
            ->delete(route('expense-categories.destroy', $category))
            ->assertRedirect();

        $this->assertDatabaseMissing('expense_categories', ['id' => $category->id]);
    }

    // -----------------------------------------------------------------
    // Forms render
    // -----------------------------------------------------------------

    public function test_the_create_and_edit_forms_render(): void
    {
        $accountant = $this->userWithRole(Role::ACCOUNTANT);
        $expense = Expense::factory()->create(['description' => 'Existing entry']);

        $this->actingAs($accountant)->get(route('expenses.create'))->assertOk()->assertSee('New expense');
        $this->actingAs($accountant)->get(route('expenses.edit', $expense))
            ->assertOk()->assertSee('Existing entry');
        $this->actingAs($accountant)->get(route('expenses.show', $expense))
            ->assertOk()->assertSee($expense->expense_reference);
        $this->actingAs($accountant)->get(route('expense-categories.index'))->assertOk();
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_billing_staff_cannot_reach_expenses(): void
    {
        $staff = $this->userWithRole(Role::BILLING_STAFF);

        $this->actingAs($staff)->get(route('expenses.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('expenses.create'))->assertForbidden();
        $this->actingAs($staff)->get(route('expense-categories.index'))->assertForbidden();
    }

    public function test_a_technician_cannot_reach_expenses(): void
    {
        $this->actingAs($this->userWithRole(Role::TECHNICIAN))
            ->get(route('expenses.index'))
            ->assertForbidden();
    }

    public function test_an_administrator_can_reach_expenses(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMINISTRATOR))
            ->get(route('expenses.index'))
            ->assertOk();
    }
}
