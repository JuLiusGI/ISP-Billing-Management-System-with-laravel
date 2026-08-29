<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * The dashboard currently reports on the only domain that exists: staff
     * accounts. Customer, billing and revenue analytics are added as their
     * modules land. Every figure is read from the database; none are stubbed.
     */
    public function index(Request $request): View
    {
        return view('dashboard.index', [
            'totalUsers' => User::count(),
            'activeUsers' => User::where('status', UserStatus::Active)->count(),
            'suspendedUsers' => User::where('status', UserStatus::Suspended)->count(),
            'recentUsers' => User::with('roles')->latest()->limit(5)->get(),
        ]);
    }
}
