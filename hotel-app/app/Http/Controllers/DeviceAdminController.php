<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\DeviceRegistry;
use App\Support\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DeviceAdminController
{
    public function show(DeviceRegistry $devices): View
    {
        return view('admin-devices', ['devices' => $devices->list()]);
    }

    public function revoke(Request $request, DeviceRegistry $devices, SecurityAudit $audit): RedirectResponse
    {
        $validated = $request->validate(['device_id' => ['required', 'string', 'uuid']]);
        $result = $devices->deactivate($validated['device_id']);

        if ($result === 'deactivated') {
            $audit->record('device_revoked', null, $request->ip(), ['device_id' => $validated['device_id']]);
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
