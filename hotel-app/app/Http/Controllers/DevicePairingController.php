<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\DevicePairing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DevicePairingController
{
    public function show(): View
    {
        return view('device-pair');
    }

    public function store(Request $request, DevicePairing $pairing): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $result = $pairing->activate($validated['code']);

        return match ($result) {
            'activated' => redirect('/device/pair')->with('status', 'Device paired.'),
            'already_used' => back()->withErrors(['code' => 'That pairing code has already been used.']),
            'expired' => back()->withErrors(['code' => 'That pairing code has expired.']),
            'not_found' => back()->withErrors(['code' => 'Unknown pairing code.']),
            'invalid_input' => back()->withErrors(['code' => 'Check the pairing code and try again.']),
            default => back()->withErrors(['code' => 'Pairing failed. Private details withheld.']),
        };
    }
}
