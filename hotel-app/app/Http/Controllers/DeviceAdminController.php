<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Audit;
use App\Security\Staff;
use App\Support\DevicePairing;
use App\Support\DeviceRegistry;
use App\Support\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class DeviceAdminController
{
    public function show(): View
    {
        $devices = DB::table('devices')->orderBy('active', 'desc')->orderBy('name')->get()->all();
        $sessions = DB::table('device_sessions')->whereNull('revoked_at')->where('expires_at', '>', now('UTC'))->get()->groupBy('device_id');

        return view('admin.devices', ['devices' => $devices, 'sessions' => $sessions]);
    }

    public function enroll(Request $request, DeviceRegistry $devices): RedirectResponse
    {
        $v = $request->validate(['name' => ['required', 'string', 'max:64'], 'mode' => ['required', 'in:tablet,kiosk,kitchen,collection,cashier']]);
        $result = $devices->enroll($v['name'], $v['mode'], bin2hex(random_bytes(24)));
        if ($result['result'] !== 'enrolled') {
            return back()->withErrors(['name' => 'Could not enrol that device. Check the name and type.']);
        }
        Audit::record('device_enrolled', (string) Staff::id($request), ['device_id' => $result['deviceId'], 'mode' => $v['mode']]);

        return redirect('/admin/devices')->with('pairing', ['name' => $v['name'], 'code' => $result['code']]);
    }

    public function code(Request $request, string $deviceId, DevicePairing $pairing): RedirectResponse
    {
        $d = DB::table('devices')->where('id', $deviceId)->where('active', 1)->first();
        abort_if($d === null, 404);
        $code = $pairing->issue($deviceId, 15);
        Audit::record('device_pairing_code_issued', (string) Staff::id($request), ['device_id' => $deviceId]);

        return redirect('/admin/devices')->with('pairing', ['name' => $d->name, 'code' => $code]);
    }

    public function revokeSession(Request $request, string $sessionId, DeviceRegistry $devices): RedirectResponse
    {
        $result = $devices->revokeSession($sessionId);
        Audit::record('device_session_revoked', (string) Staff::id($request), ['session_id' => $sessionId, 'result' => $result]);

        return back()->with('status', $result === 'revoked' ? 'Session signed out. The device must be paired again.' : 'That session was already ended.');
    }

    public function revoke(Request $request, DeviceRegistry $devices, SecurityAudit $audit): RedirectResponse
    {
        $validated = $request->validate(['device_id' => ['required', 'string', 'uuid']]);
        $result = $devices->deactivate($validated['device_id']);

        if ($result === 'deactivated') {
            $audit->record('device_revoked', Staff::id($request), $request->ip(), ['device_id' => $validated['device_id']]);
        }

        return match ($result) {
            'deactivated' => redirect('/admin/devices')->with('status', 'Device revoked.'),
            'already_inactive' => back()->withErrors(['device_id' => 'That device is already inactive.']),
            'not_found' => back()->withErrors(['device_id' => 'Unknown device.']),
            'invalid_input' => back()->withErrors(['device_id' => 'Check the device and try again.']),
            default => back()->withErrors(['device_id' => 'Revocation failed. Private details withheld.']),
        };
    }
}
