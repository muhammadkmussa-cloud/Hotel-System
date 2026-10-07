<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Security\DeviceContext;
use App\Support\DevicePairing;
use App\Support\DeviceRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class DevicePairingController
{
    public const SESSION_DAYS = 180;

    public function show(Request $request, DeviceContext $context): View
    {
        return view('device-pair', ['device' => $context->resolve($request)]);
    }

    public function store(Request $request, DevicePairing $pairing, DeviceRegistry $devices): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $result = $pairing->activate($validated['code']);
        $status = $result['status'];

        if ($status === 'activated') {
            $token = bin2hex(random_bytes(32));
            if ($devices->createSession($result['deviceId'], $token, self::SESSION_DAYS * 24 * 60) !== 'created') {
                return back()->withErrors(['code' => 'Pairing failed. Ask staff for a new code.']);
            }
            $mode = (string) DB::table('devices')->where('id', $result['deviceId'])->value('mode');
            $target = match ($mode) {
                'tablet' => '/table',
                'kiosk' => '/kiosk',
                'kitchen' => '/kitchen',
                'collection' => '/collection',
                default => '/staff',
            };

            return redirect($target)->withCookie(cookie(DeviceContext::COOKIE, $token, self::SESSION_DAYS * 24 * 60, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        return match ($status) {
            'already_used' => back()->withErrors(['code' => 'That pairing code has already been used.']),
            'expired' => back()->withErrors(['code' => 'That pairing code has expired. Ask staff for a new one.']),
            'not_found' => back()->withErrors(['code' => 'Unknown pairing code.']),
            'invalid_input' => back()->withErrors(['code' => 'Check the pairing code and try again.']),
            default => back()->withErrors(['code' => 'Pairing failed. Private details withheld.']),
        };
    }

    public function forget(Request $request): RedirectResponse
    {
        return redirect('/device/pair')->withCookie(cookie()->forget(DeviceContext::COOKIE));
    }
}
