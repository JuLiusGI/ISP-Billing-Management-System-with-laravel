<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The money reports.
 *
 * Every figure here is aggregated by the database. Reporting is the one place
 * where pulling rows into PHP to add them up looks harmless and then falls over
 * once a real customer base exists, so totals are SUM() and groupings are
 * GROUP BY throughout.
 *
 * Two rules hold across all of it:
 *   - Revenue means completed payments. Reversed and cancelled ones stay in the
 *     table for the audit trail and must never be counted as money received.
 *   - Cancelled and void invoices carry no balance and are excluded from
 *     receivables and ageing.
 */
class FinancialReportService
{
    /**
     * Money actually received in the period, with its shape over time.
     *
     * @return array{total: string, count: int, average: string, byMethod: Collection<int, object>, overTime: Collection<int, object>}
     */
    public function revenue(Carbon $from, Carbon $to, ?string $method = null): array
    {
        $base = fn () => Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->when($method, fn ($q) => $q->where('payment_method', $method));

        $total = $this->money($base()->sum('amount'));
        $count = $base()->count();

        return [
            'total' => $total,
            'count' => $count,
            'average' => $count > 0 ? bcdiv($total, (string) $count, 2) : '0.00',
            'byMethod' => $base()
                ->groupBy('payment_method')
                ->orderByDesc('total')
                ->get(['payment_method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as entries')]),
            'overTime' => $this->overTime($base(), 'payment_date', 'amount', $from, $to),
        ];
    }

    /**
     * Invoicing activity: what was billed, and where it ended up.
     *
     * @return array{byStatus: Collection<int, object>, invoiced: string, paid: string, outstanding: string, count: int}
     */
    public function billing(Carbon $from, Carbon $to): array
    {
        $base = fn () => Invoice::query()
            ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()]);

        $byStatus = $base()
            ->groupBy('status')
            ->get([
                'status',
                DB::raw('COUNT(*) as entries'),
                DB::raw('SUM(total_amount) as total'),
                DB::raw('SUM(balance_due) as balance'),
            ]);

        // Cancelled and void invoices were never really billed, so they are
        // reported separately rather than inflating the invoiced figure.
        $live = $base()->whereNotIn('status', [InvoiceStatus::Cancelled->value, InvoiceStatus::Void->value]);

        return [
            'byStatus' => $byStatus,
            'invoiced' => $this->money((clone $live)->sum('total_amount')),
            'paid' => $this->money((clone $live)->sum('amount_paid')),
            'outstanding' => $this->money((clone $live)->sum('balance_due')),
            'count' => $base()->count(),
        ];
    }

    /**
     * Receivables as they stand right now, bucketed by how long they have been
     * outstanding. Not date-filtered: ageing is a statement of the present.
     *
     * @return array{buckets: array<string, array{count: int, total: string}>, total: string, topDebtors: Collection<int, object>}
     */
    public function outstanding(): array
    {
        $buckets = [
            'Not yet due' => [null, -1],
            '1-30 days' => [0, 30],
            '31-60 days' => [31, 60],
            '61-90 days' => [61, 90],
            'Over 90 days' => [91, null],
        ];

        $results = [];

        foreach ($buckets as $label => [$minDays, $maxDays]) {
            $query = $this->openInvoices();

            if ($label === 'Not yet due') {
                $query->whereDate('due_date', '>=', now()->toDateString());
            } else {
                $query->whereDate('due_date', '<', now()->toDateString());

                if ($minDays !== null) {
                    $query->whereRaw('DATEDIFF(CURDATE(), due_date) >= ?', [$minDays]);
                }
                if ($maxDays !== null) {
                    $query->whereRaw('DATEDIFF(CURDATE(), due_date) <= ?', [$maxDays]);
                }
            }

            $results[$label] = [
                'count' => (clone $query)->count(),
                'total' => $this->money((clone $query)->sum('balance_due')),
            ];
        }

        return [
            'buckets' => $results,
            'total' => $this->money($this->openInvoices()->sum('balance_due')),
            'topDebtors' => $this->openInvoices()
                ->join('customers', 'customers.id', '=', 'invoices.customer_id')
                ->groupBy('customers.id', 'customers.account_number', 'customers.first_name', 'customers.last_name')
                ->orderByDesc('balance')
                ->limit(10)
                ->get([
                    'customers.id as customer_id',
                    'customers.account_number',
                    'customers.first_name',
                    'customers.last_name',
                    DB::raw('SUM(invoices.balance_due) as balance'),
                    DB::raw('COUNT(*) as invoices'),
                ]),
        ];
    }

    /**
     * Invoices past their due date, grouped by how far past.
     *
     * @return array{total: string, count: int, buckets: Collection<int, object>}
     */
    public function overdue(): array
    {
        $base = fn () => $this->openInvoices()->whereDate('due_date', '<', now()->toDateString());

        return [
            'total' => $this->money($base()->sum('balance_due')),
            'count' => $base()->count(),
            'buckets' => $base()
                ->selectRaw("CASE
                        WHEN DATEDIFF(CURDATE(), due_date) <= 30 THEN '1-30 days'
                        WHEN DATEDIFF(CURDATE(), due_date) <= 60 THEN '31-60 days'
                        WHEN DATEDIFF(CURDATE(), due_date) <= 90 THEN '61-90 days'
                        ELSE 'Over 90 days'
                    END as bucket")
                ->selectRaw('COUNT(*) as entries')
                ->selectRaw('SUM(balance_due) as total')
                ->groupBy('bucket')
                ->orderByDesc('total')
                ->get(),
        ];
    }

    /**
     * Operating costs for the period.
     *
     * @return array{total: string, count: int, byCategory: Collection<int, object>, overTime: Collection<int, object>}
     */
    public function expenses(Carbon $from, Carbon $to, ?int $categoryId = null): array
    {
        $base = fn () => Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($categoryId, fn ($q) => $q->where('expense_category_id', $categoryId));

        return [
            'total' => $this->money($base()->sum('amount')),
            'count' => $base()->count(),
            'byCategory' => $base()
                ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
                ->groupBy('expense_categories.id', 'expense_categories.name')
                ->orderByDesc('total')
                ->get([
                    'expense_categories.name as name',
                    DB::raw('SUM(expenses.amount) as total'),
                    DB::raw('COUNT(*) as entries'),
                ]),
            'overTime' => $this->overTime($base(), 'expense_date', 'amount', $from, $to),
        ];
    }

    /**
     * Gross revenue less expenses, per MASTER_SPEC §19.
     *
     * Computed with bcmath rather than float subtraction: this is the figure
     * that gets reported upward, and it must reconcile exactly with the two
     * numbers above it.
     *
     * @return array{grossRevenue: string, expenses: string, net: string, margin: string, months: Collection<int, object>}
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $revenue = $this->revenue($from, $to);
        $expenses = $this->expenses($from, $to);

        $gross = $revenue['total'];
        $spend = $expenses['total'];
        $net = bcsub($gross, $spend, 2);

        return [
            'grossRevenue' => $gross,
            'expenses' => $spend,
            'net' => $net,
            'margin' => bccomp($gross, '0', 2) === 1
                ? bcmul(bcdiv($net, $gross, 4), '100', 2)
                : '0.00',
            'months' => $this->monthlyComparison($from, $to),
        ];
    }

    /**
     * Normalises a database SUM() to a two-decimal string.
     *
     * SUM() over no rows comes back as 0 rather than 0.00, which then reads
     * differently from every other money figure on the page. Done with bcadd
     * rather than a float cast so no precision is lost on the way through.
     */
    private function money(mixed $value): string
    {
        return bcadd((string) ($value ?: '0'), '0', 2);
    }

    /**
     * Revenue and spend side by side per month, so a loss-making month is
     * visible rather than averaged away by the period total.
     *
     * @return Collection<int, object>
     */
    private function monthlyComparison(Carbon $from, Carbon $to): Collection
    {
        $revenue = Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('period')
            ->get([
                DB::raw("DATE_FORMAT(payment_date, '%Y-%m') as period"),
                DB::raw('SUM(amount) as total'),
            ])
            ->pluck('total', 'period');

        $spend = Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('period')
            ->get([
                DB::raw("DATE_FORMAT(expense_date, '%Y-%m') as period"),
                DB::raw('SUM(amount) as total'),
            ])
            ->pluck('total', 'period');

        return $revenue->keys()->merge($spend->keys())->unique()->sort()->values()
            ->map(function (string $period) use ($revenue, $spend): object {
                $in = (string) ($revenue[$period] ?? '0.00');
                $out = (string) ($spend[$period] ?? '0.00');

                return (object) [
                    'period' => $period,
                    'revenue' => $in,
                    'expenses' => $out,
                    'net' => bcsub($in, $out, 2),
                ];
            });
    }

    /**
     * Groups a money column by day for short ranges and by month for long
     * ones, so a year-long report does not return 365 rows to chart.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Collection<int, object>
     */
    private function overTime($query, string $dateColumn, string $amountColumn, Carbon $from, Carbon $to): Collection
    {
        $byDay = $from->diffInDays($to) <= 62;
        $format = $byDay ? '%Y-%m-%d' : '%Y-%m';

        return $query
            ->groupBy('period')
            ->orderBy('period')
            ->get([
                DB::raw("DATE_FORMAT({$dateColumn}, '{$format}') as period"),
                DB::raw("SUM({$amountColumn}) as total"),
                DB::raw('COUNT(*) as entries'),
            ]);
    }

    /**
     * Invoices that still represent money owed.
     *
     * @return Builder<Invoice>
     */
    private function openInvoices()
    {
        // Qualified: customers has a status column too, and the top-debtors
        // query joins the two tables.
        return Invoice::query()->whereIn('invoices.status', array_map(
            fn (InvoiceStatus $status) => $status->value,
            InvoiceStatus::outstanding()
        ));
    }
}
