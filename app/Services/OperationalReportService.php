<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\ServiceStatusLog;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The non-money reports: who the customers are and what state their lines are
 * in. Aggregated in SQL for the same reason as the financial ones.
 */
class OperationalReportService
{
    /**
     * @return array{total: int, byStatus: Collection<int, object>, byType: Collection<int, object>, newInPeriod: int, growth: Collection<int, object>, activeShare: int}
     */
    public function customers(Carbon $from, Carbon $to): array
    {
        return [
            'total' => Customer::count(),
            'byStatus' => Customer::query()
                ->groupBy('status')
                ->orderByDesc('entries')
                ->get(['status', DB::raw('COUNT(*) as entries')]),
            'byType' => Customer::query()
                ->groupBy('customer_type')
                ->orderByDesc('entries')
                ->get(['customer_type', DB::raw('COUNT(*) as entries')]),
            'newInPeriod' => Customer::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
            'growth' => Customer::query()
                ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->groupBy('period')
                ->orderBy('period')
                ->get([
                    DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period"),
                    DB::raw('COUNT(*) as entries'),
                ]),
            'activeShare' => Customer::where('status', CustomerStatus::Active)->count(),
        ];
    }

    /**
     * @return array{total: int, byStatus: Collection<int, object>, byPlan: Collection<int, object>, byConnection: Collection<int, object>, changes: Collection<int, object>, monthlyRecurring: string}
     */
    public function services(Carbon $from, Carbon $to): array
    {
        return [
            'total' => Subscription::count(),
            'byStatus' => Subscription::query()
                ->groupBy('status')
                ->orderByDesc('entries')
                ->get(['status', DB::raw('COUNT(*) as entries')]),
            'byPlan' => Subscription::query()
                ->join('internet_plans', 'internet_plans.id', '=', 'subscriptions.internet_plan_id')
                ->groupBy('internet_plans.id', 'internet_plans.name')
                ->orderByDesc('entries')
                ->get([
                    'internet_plans.name as name',
                    DB::raw('COUNT(*) as entries'),
                    DB::raw('SUM(subscriptions.monthly_rate) as recurring'),
                ]),
            'byConnection' => Subscription::query()
                ->groupBy('connection_type')
                ->orderByDesc('entries')
                ->get(['connection_type', DB::raw('COUNT(*) as entries')]),

            // Status changes in the period, so a spike in suspensions is
            // visible next to the standing totals.
            'changes' => ServiceStatusLog::query()
                ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->groupBy('to_status')
                ->orderByDesc('entries')
                ->get(['to_status', DB::raw('COUNT(*) as entries')]),

            // Committed monthly income from lines that are actually running.
            'monthlyRecurring' => (string) Subscription::query()
                ->where('status', SubscriptionStatus::Active)
                ->sum(DB::raw('monthly_rate - discount_amount')),
        ];
    }
}
