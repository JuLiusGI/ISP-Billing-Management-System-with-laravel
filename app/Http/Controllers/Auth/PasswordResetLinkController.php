<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        // Always report the same outcome. Distinguishing "sent" from "no such
        // address" would let anyone enumerate valid staff accounts.
        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->with('status', __(Password::RESET_LINK_SENT));
    }
}
