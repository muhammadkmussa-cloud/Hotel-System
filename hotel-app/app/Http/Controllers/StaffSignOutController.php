<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SecurityAudit;
use App\Support\StaffSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class StaffSignOutController
{
    public function store(Request $request, StaffSessions $sessions, SecurityAudit $audit): RedirectResponse
    {
        if ($request->hasSession()) {
            $staffUserId = is_string($request->session()->get('staff_user_id')) ? $request->session()->get('staff_user_id') : null;
            $sessionRowId = $request->session()->get('staff_session_id');
            if (is_string($sessionRowId) && $sessionRowId !== '') {
                // Revoke server-side first; a database failure must not leave the
                // browser session alive, so the local session is invalidated either way.
                try {
                    $sessions->revoke($sessionRowId);
                } catch (\Throwable) {
                    // Fall through to invalidate the local session.
                }
            }
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $audit->record('logout', $staffUserId, $request->ip());
        }

        return redirect('/staff/sign-in')->with('status', 'Signed out.');
    }
}
