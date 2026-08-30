<?php

namespace App\Http\Controllers;

use App\Enums\CustomerAccountStatus;
use App\Enums\CustomerConnectionStatus;
use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customers,
        private readonly PaymentService $payments,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->with('primaryAddress')
            // Archived rows are opt-in, so the default list is the live one.
            ->when($request->boolean('archived'), fn (Builder $q) => $q->onlyTrashed())
            ->search($request->string('search')->toString() ?: null)
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('account_status'), fn (Builder $q) => $q->where('account_status', $request->string('account_status')))
            ->when($request->filled('type'), fn (Builder $q) => $q->where('customer_type', $request->string('type')))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'statuses' => CustomerStatus::cases(),
            'accountStatuses' => CustomerAccountStatus::cases(),
            'types' => CustomerType::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.create', $this->formOptions());
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = $this->customers->create(
            $request->customerAttributes(),
            $request->addressAttributes(),
            $request->contactAttributes(),
            $request->file('photo'),
            $request->user(),
        );

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', "{$customer->full_name} has been added as {$customer->account_number}.");
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load([
            'primaryAddress', 'addresses', 'contacts', 'createdBy',
            'subscriptions.internetPlan',
            'serviceStatusLogs.changedBy',
        ]);

        return view('customers.show', [
            'customer' => $customer,
            // Billing history is read live; it fills in as those modules land.
            'invoices' => $customer->invoices()->latest('invoice_date')->limit(10)->get(),
            'payments' => $customer->payments()->latest('payment_date')->limit(10)->get(),
            'outstandingBalance' => $customer->outstandingBalance(),
            'totalInvoiced' => $customer->invoices()->sum('total_amount'),
            'totalPaid' => $customer->payments()->completed()->sum('amount'),
            // Money received but not yet applied to any invoice.
            'availableCredit' => $this->payments->availableCreditFor($customer),
        ]);
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.edit', $this->formOptions() + [
            'customer' => $customer->load('primaryAddress', 'contacts'),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->customers->update(
            $customer,
            $request->customerAttributes(),
            $request->addressAttributes(),
            $request->contactAttributes(),
            $request->file('photo'),
        );

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', "{$customer->full_name} has been updated.");
    }

    /**
     * Serves a customer's photo through the same policy that guards the rest
     * of their record.
     *
     * Photos live on the private disk precisely so they cannot be fetched off
     * the filesystem by URL alone. Older photos written before the move are
     * still read from the public disk so existing records keep working.
     */
    public function photo(Customer $customer): StreamedResponse
    {
        $this->authorize('view', $customer);

        abort_if(blank($customer->photo_path), 404);

        foreach ([CustomerService::PHOTO_DISK, 'public'] as $disk) {
            if (Storage::disk($disk)->exists($customer->photo_path)) {
                return Storage::disk($disk)->response(
                    $customer->photo_path,
                    null,
                    // Private: a shared cache must not hold a customer's photo.
                    ['Cache-Control' => 'private, max-age=3600']
                );
            }
        }

        abort(404);
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        try {
            $this->customers->archive($customer);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('customers.index')
            ->with('success', "{$customer->full_name} has been archived.");
    }

    public function restore(int $customer): RedirectResponse
    {
        $archived = Customer::onlyTrashed()->findOrFail($customer);

        $this->authorize('restore', $archived);

        $this->customers->restore($archived);

        return redirect()
            ->route('customers.show', $archived)
            ->with('success', "{$archived->full_name} has been restored.");
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'statuses' => CustomerStatus::cases(),
            'accountStatuses' => CustomerAccountStatus::cases(),
            'connectionStatuses' => CustomerConnectionStatus::cases(),
            'types' => CustomerType::cases(),
        ];
    }
}
