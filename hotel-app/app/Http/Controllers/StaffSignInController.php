<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\StaffAuthenticator;
use App\Support\SecurityAudit;
use App\Support\StaffSessions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class StaffSignInController
{
    public function show(): View
    {
        return view('staff-sign-in');
    }

    public function store(Request $request, StaffAuthenticator $authenticator, StaffSessions $sessions, SecurityAudit $audit, Repository $config): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string'],
        ]);

        try {
            $staff = $authenticator->attempt($validated['email'], $validated['password']);
        } catch (Throwable) {
            $staff = null;
        }

        if ($staff === null) {
            $audit->record('login_failed', null, $request->ip());
            // Safe, non-specific error: never disclose which field was wrong.
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        // Rotate the session identifier and CSRF token on privilege change,
        // then register the revocable server-side session row.
        $request->session()->regenerate();
        $lifetime = (int) $config->get('session.lifetime', 120);
        $sessionRowId = $sessions->start((string) $staff->getKey(), $request->session()->getId(), $lifetime);
        $request->session()->put('staff_user_id', (string) $staff->getKey());
        $request->session()->put('staff_session_id', $sessionRowId);
        $audit->record('login_succeeded', (string) $staff->getKey(), $request->ip());

        return redirect()->intended('/staff')->with('status', 'Signed in.');
    }
}
