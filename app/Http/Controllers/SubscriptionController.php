<?php

namespace App\Http\Controllers;

use App\Enums\ConnectionType;
use App\Enums\SubscriptionStatus;
use App\Http\Requests\ChangeSubscriptionStatusRequest;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Subscription::class);

        $subscriptions = Subscription::query()
            ->with(['customer', 'internetPlan'])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search').'%';

                $query->where(function (Builder $q) use ($term): void {
                    $q->where('subscription_code', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhereHas('customer', fn (Builder $c) => $c
                            ->where('account_number', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term));
                });
            })
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('plan'), fn (Builder $q) => $q->where('internet_plan_id', $request->integer('plan')))
            ->latest('start_date')
            ->paginate(15)
            ->withQueryString();

        return view('subscriptions.index', [
            'subscriptions' => $subscriptions,
            'statuses' => SubscriptionStatus::cases(),
            'plans' => InternetPlan::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Subscription::class);

        return view('subscriptions.create', $this->formOptions() + [
            // Reached from a customer profile, the customer arrives preselected.
            'selectedCustomer' => $request->filled('customer')
                ? Customer::find($request->integer('customer'))
                : null,
        ]);
    }

    public function store(StoreSubscriptionRequest $request): RedirectResponse
    {
        $subscription = $this->subscriptions->create($request->validated(), $request->user());

        return redirect()
            ->route('subscriptions.show', $subscription)
            ->with('success', "Subscription {$subscription->subscription_code} has been created.");
    }

    public function show(Subscription $subscription): View
    {
        $this->authorize('view', $subscription);

        return view('subscriptions.show', [
            'subscription' => $subscription->load([
                'customer.primaryAddress',
                'internetPlan',
                'serviceStatusLogs.changedBy',
            ]),
        ]);
    }

    public function edit(Subscription $subscription): View
    {
        $this->authorize('update', $subscription);

        return view('subscriptions.edit', $this->formOptions() + [
            'subscription' => $subscription->load('customer', 'internetPlan'),
        ]);
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $this->subscriptions->update($subscription, $request->validated());

        return redirect()
            ->route('subscriptions.show', $subscription)
            ->with('success', "Subscription {$subscription->subscription_code} has been updated.");
    }

    /**
     * Single entry point for activate, suspend, reactivate, expire and cancel.
     * The enum decides which moves are legal, so an illegal one is refused even
     * when the request is posted directly.
     */
    public function changeStatus(ChangeSubscriptionStatusRequest $request, Subscription $subscription): RedirectResponse
    {
        try {
            $this->subscriptions->changeStatus(
                $subscription,
                $request->targetStatus(),
                $request->input('reason'),
                $request->user(),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            "Subscription {$subscription->subscription_code} is now {$subscription->refresh()->status->label()}."
        );
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'customers' => Customer::orderBy('last_name')->orderBy('first_name')->get(),
            'plans' => InternetPlan::active()->orderBy('monthly_price')->get(),
            'connectionTypes' => ConnectionType::cases(),
        ];
    }
}
