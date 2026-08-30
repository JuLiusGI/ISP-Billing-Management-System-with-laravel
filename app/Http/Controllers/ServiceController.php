<?php

namespace App\Http\Controllers;

use App\Contracts\ServiceProvisioner;
use App\Enums\SubscriptionStatus;
use App\Models\InternetPlan;
use App\Models\ServiceStatusLog;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The operational view of subscriptions.
 *
 * SubscriptionController owns the contract: who is subscribed to what, at what
 * price. This owns the line: which services are up, which are cut, and why.
 * They share a table but are asked entirely different questions, and the
 * people asking them hold different abilities.
 */
class ServiceController extends Controller
{
    public function __construct(private readonly ServiceProvisioner $provisioner) {}

    /**
     * Service board, filtered to one status at a time.
     *
     * Defaults to active because that is the list an operator watches; the
     * counts across the top make the others one click away.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Subscription::class);

        $status = SubscriptionStatus::tryFrom((string) $request->query('status')) ?? SubscriptionStatus::Active;

        $services = Subscription::query()
            ->with(['customer', 'internetPlan'])
            ->where('status', $status)
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search').'%';

                $query->where(function (Builder $q) use ($term): void {
                    $q->where('subscription_code', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('static_ip', 'like', $term)
                        ->orWhereHas('customer', fn (Builder $c) => $c
                            ->where('account_number', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term));
                });
            })
            ->when($request->filled('plan'), fn (Builder $q) => $q->where('internet_plan_id', $request->integer('plan')))
            ->when($request->filled('connection_type'), fn (Builder $q) => $q->where('connection_type', $request->string('connection_type')))
            ->orderBy('updated_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('services.index', [
            'services' => $services,
            'status' => $status,
            'counts' => $this->countsByStatus(),
            'plans' => InternetPlan::orderBy('name')->get(),
            'provisioningEnabled' => $this->provisioner->isEnabled(),
        ]);
    }

    /**
     * The status change audit trail across every customer.
     *
     * The per-subscription history already sits on its own page; this answers
     * the wider question of what changed on the network today, and whether a
     * person or the scheduler did it.
     */
    public function history(Request $request): View
    {
        $this->authorize('viewAny', Subscription::class);

        $logs = ServiceStatusLog::query()
            ->with(['customer', 'subscription', 'changedBy'])
            ->search($request->string('search')->toString() ?: null)
            ->when($request->filled('to_status'), fn (Builder $q) => $q->movedTo($request->string('to_status')))
            ->when($request->filled('source'), fn (Builder $q) => $q->bySource($request->string('source')->toString()))
            ->recordedBetween(
                $request->string('from')->toString() ?: null,
                $request->string('to')->toString() ?: null,
            )
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('services.history', [
            'logs' => $logs,
            'statuses' => SubscriptionStatus::cases(),
        ]);
    }

    /**
     * One grouped count query rather than five, so adding a status to the
     * enum does not add a round trip.
     *
     * @return array<string, int>
     */
    private function countsByStatus(): array
    {
        $counts = Subscription::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $result = [];

        foreach (SubscriptionStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }
}
