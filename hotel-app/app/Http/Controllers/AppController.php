<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Hotel;
use App\Security\DeviceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Shells for the device-facing apps (S01–S18). Data loads via /api/v1. */
final class AppController
{
    public function table(): View
    {
        return view('apps.table', ['hotel' => Hotel::name(), 'testMode' => Hotel::testMode()]);
    }

    public function waiting(Request $request, DeviceContext $devices): View|RedirectResponse
    {
        $device = $devices->resolve($request);
        if ($device === null || $device['mode'] !== 'tablet') {
            return redirect('/device/pair');
        }
        if ($devices->guest($request) !== null) {
            return redirect('/table');
        }

        return view('apps.waiting', ['hotel' => Hotel::name(), 'device' => $device]);
    }

    public function kiosk(): View
    {
        return view('apps.kiosk', ['hotel' => Hotel::name(), 'testMode' => Hotel::testMode()]);
    }

    public function kitchen(Request $request, DeviceContext $devices): View
    {
        return view('apps.kitchen', ['hotel' => Hotel::name(), 'device' => $devices->resolve($request)]);
    }

    public function collection(): View
    {
        return view('apps.collection', ['hotel' => Hotel::name()]);
    }
}
