<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * The dashboard is assembled per role.
     *
     * Panels are added only when the signed-in user holds the ability covering
     * the data behind them, and the queries for a panel they cannot see are
     * never run. A technician's dashboard therefore issues no revenue queries
     * at all rather than fetching figures and hiding them in the view.
     *
     * Every number comes from the database; none are stubbed.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $data = [];

        if ($user->can('customers.view')) {
            $data['customerStats'] = $this->dashboard->customerStats();
            $data['customerTrend'] = $this->dashboard->customerTrend();
            $data['recentCustomers'] = $this->dashboard->recentCustomers();
        }

        if ($user->can('subscriptions.view')) {
            $data['serviceStats'] = $this->dashboard->serviceStats();
            $data['serviceMix'] = $this->dashboard->serviceMix();
        }

        if ($user->can('invoices.view')) {
            $data['billingStats'] = $this->dashboard->billingStats();
            $data['invoiceMix'] = $this->dashboard->invoiceMix();
            $data['recentInvoices'] = $this->dashboard->recentInvoices();
            $data['alerts'] = $this->dashboard->alerts();
        }

        if ($user->can('payments.view')) {
            $data['recentPayments'] = $this->dashboard->recentPayments();
        }

        if ($user->can('reports.financial')) {
            $data['financialStats'] = $this->dashboard->financialStats();
            $data['revenueTrend'] = $this->dashboard->revenueTrend();
        }

        return view('dashboard.index', $data);
    }
}
