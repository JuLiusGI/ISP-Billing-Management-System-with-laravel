<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Services\FinancialReportService;
use App\Services\OperationalReportService;
use App\Services\ReportExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report screens.
 *
 * Each report is gated on the ability that covers the data it exposes rather
 * than on one blanket "reports" ability, so a role only ever sees a report over
 * records it could already read: billing staff see receivables, accountants see
 * revenue and spend, technicians see customers and services.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly FinancialReportService $financial,
        private readonly OperationalReportService $operational,
        private readonly ReportExporter $exporter,
    ) {}

    /** The hub, showing only the reports this user may open. */
    public function index(): View
    {
        return view('reports.index');
    }

    // -----------------------------------------------------------------
    // Financial
    // -----------------------------------------------------------------

    public function revenue(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $method = $request->string('method')->toString() ?: null;

        $report = $this->financial->revenue($from, $to, $method);

        if ($request->query('export') === 'csv') {
            return $this->exporter->csv(
                'revenue-report',
                ['Period', 'Payments', 'Total'],
                $report['overTime']->map(fn ($row) => [$row->period, $row->entries, $row->total])
            );
        }

        return view('reports.revenue', compact('report', 'from', 'to', 'method') + [
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function payments(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $query = Payment::query()
            ->with(['customer', 'receivedBy'])
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->when($request->filled('method'), fn ($q) => $q->where('payment_method', $request->string('method')))
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
                // Reversed payments are shown only when asked for; otherwise
                // the report would overstate what was taken.
                fn ($q) => $q->where('status', PaymentStatus::Completed)
            );

        if ($request->query('export') === 'csv') {
            return $this->exporter->csv(
                'payment-report',
                ['Reference', 'Date', 'Customer', 'Account', 'Method', 'Status', 'Amount'],
                (clone $query)->orderBy('payment_date')->cursor()->map(fn (Payment $p) => [
                    $p->payment_reference,
                    $p->payment_date->toDateString(),
                    $p->customer?->full_name,
                    $p->customer?->account_number,
                    $p->payment_method->label(),
                    $p->status->label(),
                    $p->amount,
                ])
            );
        }

        return view('reports.payments', [
            'payments' => (clone $query)->orderByDesc('payment_date')->orderByDesc('id')
                ->paginate(25)->withQueryString(),
            'total' => (string) (clone $query)->sum('amount'),
            'count' => (clone $query)->count(),
            'from' => $from,
            'to' => $to,
            'methods' => PaymentMethod::cases(),
            'statuses' => PaymentStatus::cases(),
        ]);
    }

    public function billing(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $report = $this->financial->billing($from, $to);

        if ($request->query('export') === 'csv') {
            return $this->exporter->csv(
                'billing-report',
                ['Status', 'Invoices', 'Total billed', 'Outstanding'],
                $report['byStatus']->map(fn ($row) => [
                    $row->status->label(), $row->entries, $row->total, $row->balance,
                ])
            );
        }

        return view('reports.billing', compact('report', 'from', 'to'));
    }

    public function outstanding(Request $request): View|StreamedResponse
    {
        $report = $this->financial->outstanding();

        if ($request->query('export') === 'csv') {
            return $this->exporter->csv(
                'outstanding-report',
                ['Age', 'Invoices', 'Balance'],
                collect($report['buckets'])->map(fn ($row, $label) => [$label, $row['count'], $row['total']])
            );
        }

        return view('reports.outstanding', compact('report'));
    }

    public function overdue(Request $request): View|StreamedResponse
    {
        $report = $this->financial->overdue();

        if ($request->query('export') === 'csv') {
            return $this->exporter->csv(
                'overdue-report',
                ['Age', 'Invoices', 'Balance'],
                $report['buckets']->map(fn ($row) => [$row->bucket, $row->entries, $row->total])
            );
        }

        return view('reports.overdue', compact('report'));
    }

    public function expenses(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $categoryId = $request->filled('category') ? $request->integer('category') : null;

        $report = $this->financial->expenses($from, $to, $categoryId);

        if ($request->query('export') === 'csv') {
            return $this->exporter->csv(
                'expense-report',
                ['Category', 'Entries', 'Total'],
                $report['byCategory']->map(fn ($row) => [$row->name, $row->entries, $row->total])
            );
        }

        return view('reports.expenses', compact('report', 'from', 'to', 'categoryId') + [
            'categories' => ExpenseCategory::orderBy('name')->get(),
        ]);
    }

    public function summary(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $report = $this->financial->summary($from, $to);

        if ($request->query('export') === 'csv') {
            return $this->exporter->csv(
                'financial-summary',
                ['Month', 'Revenue', 'Expenses', 'Net'],
                $report['months']->map(fn ($row) => [$row->period, $row->revenue, $row->expenses, $row->net])
            );
        }

        return view('reports.summary', compact('report', 'from', 'to'));
    }

    // -----------------------------------------------------------------
    // Operational
    // -----------------------------------------------------------------

    public function customers(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $report = $this->operational->customers($from, $to);

        if ($request->query('export') === 'csv') {
            return $this->exporter->csv(
                'customer-report',
                ['Status', 'Customers'],
                $report['byStatus']->map(fn ($row) => [$row->status->label(), $row->entries])
            );
        }

        return view('reports.customers', compact('report', 'from', 'to'));
    }

    public function services(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $report = $this->operational->services($from, $to);

        if ($request->query('export') === 'csv') {
            return $this->exporter->csv(
                'service-report',
                ['Plan', 'Services', 'Monthly recurring'],
                $report['byPlan']->map(fn ($row) => [$row->name, $row->entries, $row->recurring])
            );
        }

        return view('reports.services', compact('report', 'from', 'to'));
    }

    /**
     * The reporting period, defaulting to the last six months.
     *
     * Dates are clamped rather than validated away: a report is read-only, and
     * a nonsense range should show an empty report rather than an error page.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $from = $this->parseDate($request->query('from')) ?? Carbon::now()->subMonths(6)->startOfMonth();
        $to = $this->parseDate($request->query('to')) ?? Carbon::now()->endOfMonth();

        return $from->greaterThan($to) ? [$to, $from] : [$from, $to];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
