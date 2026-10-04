<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\InstallationSetup;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SetupController
{
    public function show(Request $request, InstallationSetup $setup): View|RedirectResponse
    {
        abort_unless($setup->enabled(), 404);
        if ($setup->configured()) {
            return redirect('/')->with('status', 'This installation is already configured.');
        }

        return view('setup', [
            'timezones' => InstallationSetup::ALLOWED_TIMEZONES,
            'currency' => InstallationSetup::CURRENCY,
        ]);
    }

    public function store(Request $request, InstallationSetup $setup, Repository $config): RedirectResponse
    {
        abort_unless($setup->enabled(), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'timezone' => ['required', 'string', Rule::in(InstallationSetup::ALLOWED_TIMEZONES)],
            'test_mode' => ['nullable', 'boolean'],
            'installer_secret' => ['required', 'string', 'max:128'],
        ]);

        // The setup screen is a write path; require the private installer secret
        // so it cannot be claimed by an unauthenticated visitor while enabled.
        $configuredSecret = $config->get('installation.installer_secret');
        if (! is_string($configuredSecret) || strlen($configuredSecret) < 32
            || ! hash_equals($configuredSecret, $validated['installer_secret'])
        ) {
            return back()->withInput($request->only('name', 'timezone'))->withErrors(['installer_secret' => 'Installer authorization failed.']);
        }

        $result = $setup->create($validated['name'], $validated['timezone'], $request->boolean('test_mode'));

        return match ($result) {
            'created' => redirect('/')->with('status', 'Installation configured. Disable setup access before going live.'),
            'refused_exists' => redirect('/')->with('status', 'This installation is already configured.'),
            'invalid_input' => back()->withInput($request->only('name', 'timezone'))->withErrors(['name' => 'Check the installation details and try again.']),
            default => back()->withInput($request->only('name', 'timezone'))->withErrors(['name' => 'Setup failed. Private details withheld; inspect the database and configuration.']),
        };
    }
}
