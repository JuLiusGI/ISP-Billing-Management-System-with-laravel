<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerContact;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Development sample customers.
 *
 * Skipped entirely once any customer exists, so re-seeding an environment that
 * already has real data adds nothing.
 */
class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        if (Customer::withTrashed()->exists()) {
            return;
        }

        $createdBy = User::where('email', env('SEED_ADMIN_EMAIL', 'admin@example.com'))->value('id');

        $mix = [
            [Customer::factory()->count(14), 'active'],
            [Customer::factory()->count(3)->pendingInstallation(), 'pending'],
            [Customer::factory()->count(2)->suspended(), 'suspended'],
            [Customer::factory()->count(1)->business(), 'business'],
        ];

        foreach ($mix as [$factory, $ignored]) {
            $factory->create(['created_by' => $createdBy])->each(function (Customer $customer): void {
                CustomerAddress::factory()->for($customer)->create();

                // Roughly a third of customers carry a secondary contact.
                if (fake()->boolean(35)) {
                    CustomerContact::factory()->for($customer)->create();
                }
            });
        }
    }
}
