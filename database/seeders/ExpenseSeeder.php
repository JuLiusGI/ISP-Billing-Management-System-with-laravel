<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Development sample expenses, spread across the last six months so the
 * summaries and date filters have something to show.
 *
 * Skipped entirely once any expense exists.
 */
class ExpenseSeeder extends Seeder
{
    /** Rough monthly shape of a small ISP's costs. */
    private const PATTERN = [
        'UPSTREAM' => [['Upstream bandwidth', 45000, 60000]],
        'ELECTRICITY' => [['Electricity - tower site', 6000, 12000]],
        'SALARIES' => [['Staff salaries', 60000, 80000]],
        'MAINTENANCE' => [['Line repairs and splicing', 1500, 8000]],
        'TRANSPORT' => [['Fuel for service vehicle', 1200, 4000]],
        'SUPPLIES' => [['Office supplies', 500, 2500]],
    ];

    public function run(): void
    {
        if (Expense::withTrashed()->exists()) {
            return;
        }

        $categories = ExpenseCategory::pluck('id', 'code');
        $recordedBy = User::where('email', env('SEED_ADMIN_EMAIL', 'admin@example.com'))->value('id');
        $sequence = 0;

        foreach (range(5, 0) as $monthsAgo) {
            $month = Carbon::now()->subMonths($monthsAgo);

            foreach (self::PATTERN as $code => $entries) {
                if (! isset($categories[$code])) {
                    continue;
                }

                foreach ($entries as [$description, $min, $max]) {
                    $sequence++;

                    Expense::create([
                        'expense_reference' => sprintf('EXP-%s-%06d', $month->format('Y'), $sequence),
                        'expense_category_id' => $categories[$code],
                        'description' => $description,
                        'amount' => fake()->numberBetween($min, $max),
                        'expense_date' => $month->copy()->day(fake()->numberBetween(1, 28))->toDateString(),
                        'payment_method' => fake()->randomElement([
                            PaymentMethod::Cash, PaymentMethod::BankTransfer,
                        ]),
                        'vendor' => fake()->optional(0.7)->company(),
                        'created_by' => $recordedBy,
                    ]);
                }
            }
        }
    }
}
