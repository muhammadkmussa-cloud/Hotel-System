<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SecurityAudit;
use App\Support\StaffAuthenticator;
use App\Support\StaffSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class StaffUnlockController
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! is_string($request->session()->get('staff_user_id'))) {
            return redirect('/staff/sign-in');
        }

        return view('staff-lock');
    }

    public function store(Request $request, StaffAuthenticator $authenticator, StaffSessions $sessions, SecurityAudit $audit): RedirectResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        $staffId = $request->session()->get('staff_user_id');
        $sessionRowId = $request->session()->get('staff_session_id');
        if (! is_string($staffId) || ! is_string($sessionRowId)) {
            return redirect('/staff/sign-in');
        }
        // A revoked or absolutely-expired row cannot be unlocked.
        if (! $sessions->active($sessionRowId, $request->session()->getId())) {
            return redirect('/staff/sign-in');
        }

        if (! $authenticator->confirmById($staffId, $validated['password'])) {
            $audit->record('unlock_failed', $staffId, $request->ip());
            // The unlock throttle is keyed on the server-side staff id, so repeated
            // guesses share one bounded bucket regardless of the session id.
            return back()->withErrors(['password' => 'Unlock failed. Check your password and try again.']);
        }

        // Rotate the id, then re-bind the server-side row to the new id.
        $request->session()->regenerate();
        $sessions->rebind($sessionRowId, $request->session()->getId());
        $audit->record('unlock_succeeded', $staffId, $request->ip());

        return redirect('/')->with('status', 'Session unlocked.');
    }
}
