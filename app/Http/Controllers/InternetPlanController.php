<?php

namespace App\Http\Controllers;

use App\Enums\PlanBillingCycle;
use App\Enums\SpeedUnit;
use App\Enums\SubscriptionStatus;
use App\Http\Requests\StoreInternetPlanRequest;
use App\Http\Requests\UpdateInternetPlanRequest;
use App\Models\InternetPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternetPlanController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', InternetPlan::class);

        $plans = InternetPlan::query()
            ->withCount([
                'subscriptions',
                'subscriptions as active_subscriptions_count' => fn (Builder $q) => $q->where('status', SubscriptionStatus::Active),
            ])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search').'%';

                $query->where(fn (Builder $q) => $q->where('name', 'like', $term)->orWhere('plan_code', 'like', $term));
            })
            ->when($request->filled('status'), fn (Builder $q) => $q->where('is_active', $request->string('status')->toString() === 'active'))
            ->when($request->filled('cycle'), fn (Builder $q) => $q->where('billing_cycle', $request->string('cycle')))
            ->orderBy('is_active', 'desc')
            ->orderBy('monthly_price')
            ->paginate(15)
            ->withQueryString();

        return view('plans.index', [
            'plans' => $plans,
            'cycles' => PlanBillingCycle::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', InternetPlan::class);

        return view('plans.create', $this->formOptions());
    }

    public function store(StoreInternetPlanRequest $request): RedirectResponse
    {
        $plan = InternetPlan::create($request->validated());

        return redirect()
            ->route('plans.index')
            ->with('success', "The {$plan->name} plan has been created.");
    }

    public function edit(InternetPlan $plan): View
    {
        $this->authorize('update', $plan);

        return view('plans.edit', $this->formOptions() + [
            'plan' => $plan->loadCount('subscriptions'),
        ]);
    }

    public function update(UpdateInternetPlanRequest $request, InternetPlan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        // Repricing never reaches existing subscriptions: each copied its rate
        // at signup. Say so explicitly, because the opposite is the assumption
        // people usually arrive with.
        $note = $plan->wasChanged('monthly_price')
            ? ' Existing subscriptions keep the rate they were signed up on.'
            : '';

        return redirect()
            ->route('plans.index')
            ->with('success', "The {$plan->name} plan has been updated.{$note}");
    }

    /**
     * Deactivating hides a plan from new signups while leaving every existing
     * subscription and invoice untouched. This is the normal way to retire one.
     */
    public function toggle(InternetPlan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $plan->update(['is_active' => ! $plan->is_active]);

        return back()->with(
            'success',
            "The {$plan->name} plan is now ".($plan->is_active ? 'active' : 'inactive').'.'
        );
    }

    public function destroy(InternetPlan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $plan->delete();

        return redirect()
            ->route('plans.index')
            ->with('success', "The {$plan->name} plan has been deleted.");
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'speedUnits' => SpeedUnit::cases(),
            'cycles' => PlanBillingCycle::cases(),
        ];
    }
}
