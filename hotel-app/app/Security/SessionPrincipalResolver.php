<?php

declare(strict_types=1);

namespace App\Security;

use App\Support\StaffSessions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Throwable;

/**
 * Resolves the signed-in staff principal from the server session and its
 * revocable server-side session row. Fails closed on any error.
 */
final class SessionPrincipalResolver implements PrincipalResolver
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly StaffSessions $sessions,
        private readonly Repository $config,
    ) {}

    public function resolve(Request $request): ?Principal
    {
        if (! $request->hasSession()) {
            return null;
        }
        $id = $request->session()->get('staff_user_id');
        $sessionRowId = $request->session()->get('staff_session_id');
        if (! is_string($id) || $id === '' || ! is_string($sessionRowId) || $sessionRowId === '') {
            return null;
        }
        if (! $this->sessions->active($sessionRowId, $request->session()->getId(), $id)) {
            return null;
        }

        // Inactivity lock: a session idle beyond the configured window is denied
        // until the staff member re-verifies their password on the unlock screen.
        $idleSeconds = $this->sessions->idleSeconds($sessionRowId);
        $limit = ((int) $this->config->get('installation.staff_idle_minutes', 30)) * 60;
        if ($idleSeconds === null || $idleSeconds > $limit) {
            return null;
        }

        try {
            $active = $this->database->connection('mysql')->table('staff_users')
                ->where('id', $id)->where('active', 1)->exists();
        } catch (Throwable) {
            return null;
        }

        if (! $active) {
            return null;
        }
        $this->sessions->touch($sessionRowId);

        return new SessionPrincipal($id);
    }
}
