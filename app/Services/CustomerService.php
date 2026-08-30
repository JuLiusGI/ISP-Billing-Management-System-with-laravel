<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Customer writes that touch more than one table.
 *
 * Kept out of the controller so the same logic is available to an importer, a
 * console command or a future API without going through HTTP.
 */
class CustomerService
{
    /**
     * Where a customer's photo lives.
     *
     * The private disk, not the public one. A photo of a named customer is
     * personal data, and anything on the public disk is served straight off
     * the filesystem with no session behind it — a random filename is
     * obscurity, not access control. CustomerController::photo() serves these
     * through the same policy that guards the rest of the record.
     */
    public const PHOTO_DISK = 'local';

    public const PHOTO_DIRECTORY = 'customers/photos';

    /**
     * How many times to retry an account number collision.
     *
     * Numbers are derived from the current maximum id, so two requests
     * arriving together can pick the same one. The unique index is what
     * actually guarantees uniqueness; this turns the resulting error into a
     * retry rather than a failed save.
     */
    private const ACCOUNT_NUMBER_ATTEMPTS = 5;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $address
     * @param  array<int, array<string, mixed>>  $contacts
     */
    public function create(array $attributes, array $address, array $contacts, ?UploadedFile $photo, User $actor): Customer
    {
        $attributes['created_by'] = $actor->id;

        if ($photo) {
            $attributes['photo_path'] = $this->storePhoto($photo);
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes, $address, $contacts): Customer {
                    $customer = Customer::create($attributes);

                    $customer->addresses()->create($address + ['type' => 'service', 'is_primary' => true]);

                    foreach ($this->presentContacts($contacts) as $contact) {
                        $customer->contacts()->create($contact);
                    }

                    return $customer;
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::ACCOUNT_NUMBER_ATTEMPTS || ! $this->isAccountNumberCollision($e)) {
                    throw $e;
                }
                // Fall through and let the model generate a fresh number.
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $address
     * @param  array<int, array<string, mixed>>  $contacts
     */
    public function update(Customer $customer, array $attributes, array $address, array $contacts, ?UploadedFile $photo): Customer
    {
        return DB::transaction(function () use ($customer, $attributes, $address, $contacts, $photo): Customer {
            if ($photo) {
                $previous = $customer->photo_path;
                $attributes['photo_path'] = $this->storePhoto($photo);

                if ($previous) {
                    $this->deletePhoto($previous);
                }
            }

            // The account number is generated, never edited.
            unset($attributes['account_number']);

            $customer->update($attributes);

            $customer->addresses()->updateOrCreate(
                ['customer_id' => $customer->id, 'type' => 'service'],
                $address + ['is_primary' => true]
            );

            // Contacts are submitted as a complete list, so replacing them is
            // what the form actually means.
            $customer->contacts()->delete();

            foreach ($this->presentContacts($contacts) as $contact) {
                $customer->contacts()->create($contact);
            }

            return $customer->refresh();
        });
    }

    private function storePhoto(UploadedFile $photo): string
    {
        return $photo->store(self::PHOTO_DIRECTORY, self::PHOTO_DISK);
    }

    /**
     * Removes a superseded photo from whichever disk holds it. The public disk
     * is checked as well so photos written before they moved are still cleaned
     * up rather than left behind.
     */
    private function deletePhoto(string $path): void
    {
        foreach ([self::PHOTO_DISK, 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }

    /**
     * Archives a customer. Soft delete rather than removal: their invoices and
     * payments must stay attributable.
     */
    public function archive(Customer $customer): void
    {
        if (bccomp($customer->outstandingBalance(), '0', 2) === 1) {
            throw new RuntimeException(
                'This customer still has an outstanding balance and cannot be archived.'
            );
        }

        $customer->delete();
    }

    public function restore(Customer $customer): void
    {
        $customer->restore();
    }

    /**
     * Drops blank contact rows the form submits for its empty slots.
     *
     * @param  array<int, array<string, mixed>>  $contacts
     * @return array<int, array<string, mixed>>
     */
    private function presentContacts(array $contacts): array
    {
        return array_values(array_filter(
            $contacts,
            fn (array $contact) => filled($contact['name'] ?? null) && filled($contact['contact_number'] ?? null)
        ));
    }

    private function isAccountNumberCollision(UniqueConstraintViolationException $e): bool
    {
        return str_contains($e->getMessage(), 'account_number');
    }
}
