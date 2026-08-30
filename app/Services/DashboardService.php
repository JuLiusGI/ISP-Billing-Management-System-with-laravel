<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The dashboard's figures.
 *
 * Split into one method per panel rather than one method returning everything,
 * because the dashboard is role-aware: a technician never sees revenue, so the
 * revenue queries should not run for them at all.
 *
 * Every number is read from the database. Nothing here is stubbed, and the
 * same exclusions the reports use apply — completed payments only, cancelled
 * and void invoices out of receivables.
 */
class DashboardService
{
    /** How many months of history the trend charts cover. */
    private const TREND_MONTHS = 12;

    /** @return array<string, int> */
    public function customerStats(): array
    {
        // Aliased to status_key on purpose: `status` carries an enum cast, and
        // an enum instance cannot be used as an array key by pluck().
        $byStatus = Customer::query()
            ->groupBy('status')
            ->get([DB::raw('status as status_key'), DB::raw('COUNT(*) as entries')])
            ->pluck('entries', 'status_key');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[CustomerStatus::Active->value] ?? 0),
            'inactive' => (int) ($byStatus[CustomerStatus::Inactive->value] ?? 0),
            'suspended' => (int) ($byStatus[CustomerStatus::Suspended->value] ?? 0),
            'newThisMonth' => Customer::where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    /** @return array<string, int> */
    public function serviceStats(): array
    {
        $byStatus = Subscription::query()
            ->groupBy('status')
            ->get([DB::raw('status as status_key'), DB::raw('COUNT(*) as entries')])
            ->pluck('entries', 'status_key');

        return [
            'active' => (int) ($byStatus[SubscriptionStatus::Active->value] ?? 0),
            'suspended' => (int) ($byStatus[SubscriptionStatus::Suspended->value] ?? 0),
            'expired' => (int) ($byStatus[SubscriptionStatus::Expired->value] ?? 0),
            'pendingInstallation' => Customer::where('status', CustomerStatus::PendingInstallation)->count(),
        ];
    }

    /** @return array<string, string> */
    public function billingStats(): array
    {
        $live = fn () => Invoice::query()
            ->whereNotIn('status', [InvoiceStatus::Cancelled->value, InvoiceStatus::Void->value]);

        return [
            'totalInvoiced' => $this->money($live()->sum('total_amount')),
            'totalPaid' => $this->money($live()->sum('amount_paid')),
            'totalOutstanding' => $this->money($this->openInvoices()->sum('balance_due')),
            'totalOverdue' => $this->money($this->openInvoices()
                ->whereDate('due_date', '<', now()->toDateString())
                ->sum('balance_due')),
        ];
    }

    /** @return array<string, string> */
    public function financialStats(): array
    {
        $revenueThisMonth = $this->money($this->completedPayments()
            ->where('payment_date', '>=', now()->startOfMonth()->toDateString())
            ->sum('amount'));

        $expensesThisMonth = $this->money(Expense::query()
            ->where('expense_date', '>=', now()->startOfMonth()->toDateString())
            ->sum('amount'));

        return [
            'revenueThisMonth' => $revenueThisMonth,
            'revenueThisYear' => $this->money($this->completedPayments()
                ->where('payment_date', '>=', now()->startOfYear()->toDateString())
                ->sum('amount')),
            'expensesThisMonth' => $expensesThisMonth,
            'netThisMonth' => bcsub($revenueThisMonth, $expensesThisMonth, 2),
        ];
    }

    /**
     * What needs someone's attention today, per MASTER_SPEC §15.
     *
     * @return array{overdueAccounts: int, overdueAmount: string, oldestUnpaid: ?Invoice, needingAttention: Collection<int, object>}
     */
    public function alerts(): array
    {
        $overdue = fn () => $this->openInvoices()->whereDate('due_date', '<', now()->toDateString());

        return [
            'overdueAccounts' => (int) (clone $overdue())->distinct()->count('customer_id'),
            'overdueAmount' => $this->money($overdue()->sum('balance_due')),
            'oldestUnpaid' => $overdue()->with('customer')->orderBy('due_date')->first(),
            'needingAttention' => $overdue()
                ->join('customers', 'customers.id', '=', 'invoices.customer_id')
                ->groupBy('customers.id', 'customers.account_number', 'customers.first_name', 'customers.last_name')
                ->orderByDesc('balance')
                ->limit(5)
                ->get([
                    'customers.id as customer_id',
                    'customers.account_number',
                    'customers.first_name',
                    'customers.last_name',
                    DB::raw('SUM(invoices.balance_due) as balance'),
                    DB::raw('MIN(invoices.due_date) as oldest_due'),
                ]),
        ];
    }

    // -----------------------------------------------------------------
    // Charts
    // -----------------------------------------------------------------

    /**
     * Revenue and payment count per month, over the trend window.
     *
     * Months with no activity are filled with zero rather than skipped: a gap
     * in a time series should read as "nothing happened", not compress the
     * axis and imply the months were adjacent.
     *
     * @return array{labels: array<int, string>, revenue: array<int, string>, payments: array<int, int>}
     */
    public function revenueTrend(): array
    {
        $rows = $this->completedPayments()
            ->where('payment_date', '>=', $this->trendStart()->toDateString())
            ->groupBy('period')
            ->get([
                DB::raw("DATE_FORMAT(payment_date, '%Y-%m') as period"),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as entries'),
            ])
            ->keyBy('period');

        $labels = [];
        $revenue = [];
        $payments = [];

        foreach ($this->trendMonths() as $month) {
            $key = $month->format('Y-m');
            $labels[] = $month->format('M Y');
            $revenue[] = $this->money($rows[$key]->total ?? 0);
            $payments[] = (int) ($rows[$key]->entries ?? 0);
        }

        return ['labels' => $labels, 'revenue' => $revenue, 'payments' => $payments];
    }

    /**
     * New customers per month over the trend window.
     *
     * @return array{labels: array<int, string>, customers: array<int, int>}
     */
    public function customerTrend(): array
    {
        $rows = Customer::query()
            ->where('created_at', '>=', $this->trendStart()->startOfMonth())
            ->groupBy('period')
            ->get([
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period"),
                DB::raw('COUNT(*) as entries'),
            ])
            ->keyBy('period');

        $labels = [];
        $customers = [];

        foreach ($this->trendMonths() as $month) {
            $labels[] = $month->format('M Y');
            $customers[] = (int) ($rows[$month->format('Y-m')]->entries ?? 0);
        }

        return ['labels' => $labels, 'customers' => $customers];
    }

    /**
     * Service mix, as labelled slices ready to chart.
     *
     * @return array{labels: array<int, string>, values: array<int, int>, colours: array<int, string>}
     */
    public function serviceMix(): array
    {
        $counts = Subscription::query()
            ->groupBy('status')
            ->get([DB::raw('status as status_key'), DB::raw('COUNT(*) as entries')])
            ->pluck('entries', 'status_key');

        $labels = [];
        $values = [];
        $colours = [];

        foreach (SubscriptionStatus::cases() as $case) {
            $count = (int) ($counts[$case->value] ?? 0);

            if ($count === 0) {
                continue;
            }

            $labels[] = $case->label();
            $values[] = $count;
            $colours[] = $this->statusColour($case->value);
        }

        return ['labels' => $labels, 'values' => $values, 'colours' => $colours];
    }

    /**
     * Invoice status distribution.
     *
     * @return array{labels: array<int, string>, values: array<int, int>, colours: array<int, string>}
     */
    public function invoiceMix(): array
    {
        $counts = Invoice::query()
            ->groupBy('status')
            ->get([DB::raw('status as status_key'), DB::raw('COUNT(*) as entries')])
            ->pluck('entries', 'status_key');

        $labels = [];
        $values = [];
        $colours = [];

        foreach (InvoiceStatus::cases() as $case) {
            $count = (int) ($counts[$case->value] ?? 0);

            if ($count === 0) {
                continue;
            }

            $labels[] = $case->label();
            $values[] = $count;
            $colours[] = $this->statusColour($case->value);
        }

        return ['labels' => $labels, 'values' => $values, 'colours' => $colours];
    }

    // -----------------------------------------------------------------
    // Recent activity
    // -----------------------------------------------------------------

    /** @return Collection<int, Payment> */
    public function recentPayments(int $limit = 5): Collection
    {
        return Payment::with('customer')
            ->latest('payment_date')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, Invoice> */
    public function recentInvoices(int $limit = 5): Collection
    {
        return Invoice::with('customer')
            ->latest('invoice_date')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, Customer> */
    public function recentCustomers(int $limit = 5): Collection
    {
        return Customer::latest('id')->limit($limit)->get();
    }

    // -----------------------------------------------------------------

    /**
     * The chart palette, keyed by status so a status is the same colour
     * wherever it appears.
     */
    private function statusColour(string $status): string
    {
        return match ($status) {
            'active', 'paid' => '#198754',
            'suspended', 'partially_paid' => '#ffc107',
            'expired', 'draft' => '#6c757d',
            'cancelled', 'void' => '#343a40',
            'overdue' => '#c62828',
            'unpaid' => '#fd7e14',
            'pending' => '#0dcaf0',
            default => '#14487f',
        };
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

    /** @return Collection<int, Carbon> */
    private function trendMonths(): Collection
    {
        return collect(range(self::TREND_MONTHS - 1, 0))
            ->map(fn (int $back) => now()->startOfMonth()->subMonths($back));
    }

    private function trendStart(): Carbon
    {
        return now()->startOfMonth()->subMonths(self::TREND_MONTHS - 1);
    }

    /** @return Builder<Payment> */
    private function completedPayments()
    {
        return Payment::query()->where('status', PaymentStatus::Completed);
    }

    /** @return Builder<Invoice> */
    private function openInvoices()
    {
        // Qualified: the attention query joins customers, which also has a
        // status column.
        return Invoice::query()->whereIn('invoices.status', array_map(
            fn (InvoiceStatus $status) => $status->value,
            InvoiceStatus::outstanding()
        ));
    }
}
