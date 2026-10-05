<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\HotelSettingsEditor;
use App\Support\IntegrationStatus;
use App\Support\PrinterDestinationConfig;
use App\Support\StationConfig;
use App\Support\TableConfig;
use App\Support\ResourceVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HotelSettingsController
{
    public function show(HotelSettingsEditor $editor, TableConfig $tables, StationConfig $stations, PrinterDestinationConfig $printers, IntegrationStatus $integrations): View
    {
        return view('hotel-settings', [
            'settings' => $editor->current(),
            'timezones' => HotelSettingsEditor::ALLOWED_TIMEZONES,
            'tables' => $tables->list(),
            'stations' => $stations->list(),
            'printers' => $printers->list(),
            'integrations' => $integrations->summary(),
        ]);
    }

    public function storePrinters(Request $request, PrinterDestinationConfig $printers): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:64'],
            'destination' => ['nullable', 'string', 'max:255'],
            'printer_id' => ['nullable', 'string', 'uuid'],
        ]);

        if ($validated['printer_id'] !== null) {
            $result = $printers->deactivate($validated['printer_id']);
        } else {
            if (($validated['name'] ?? null) === null || ($validated['destination'] ?? null) === null) {
                return back()->withInput()->withErrors(['name' => 'Provide a printer name and destination.']);
            }
            $result = $printers->create($validated['name'], $validated['destination']);
        }

        return match ($result) {
            'created', 'deactivated' => redirect('/admin/settings')->with('status', 'Printer destination updated.'),
            'duplicate' => back()->withInput()->withErrors(['name' => 'That printer name already exists.']),
            'already_inactive' => back()->withErrors(['printer_id' => 'That printer is already inactive.']),
            'not_found' => back()->withErrors(['printer_id' => 'Unknown printer.']),
            'invalid_input' => back()->withInput()->withErrors(['destination' => 'Destination must be an HTTPS URL on an allowlisted bridge host.']),
            default => back()->withInput()->withErrors(['name' => 'Printer update failed. Private details withheld.']),
        };
    }

    public function storeStations(Request $request, StationConfig $stations): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:64'],
            'kind' => ['nullable', 'string', 'in:kitchen,bar'],
            'station_id' => ['nullable', 'string', 'uuid'],
        ]);

        if ($validated['station_id'] !== null) {
            $result = $stations->deactivate($validated['station_id']);
        } else {
            if (($validated['name'] ?? null) === null || ($validated['kind'] ?? null) === null) {
                return back()->withInput()->withErrors(['name' => 'Provide a station name and kind.']);
            }
            $result = $stations->create($validated['name'], $validated['kind'], null);
        }

        return match ($result) {
            'created', 'deactivated' => redirect('/admin/settings')->with('status', 'Station configuration updated.'),
            'duplicate' => back()->withInput()->withErrors(['name' => 'That station already exists.']),
            'already_inactive' => back()->withErrors(['station_id' => 'That station is already inactive.']),
            'not_found' => back()->withErrors(['station_id' => 'Unknown station.']),
            'invalid_input' => back()->withInput()->withErrors(['name' => 'Check the station details and try again.']),
            default => back()->withInput()->withErrors(['name' => 'Station update failed. Private details withheld.']),
        };
    }

    public function storeTables(Request $request, TableConfig $tables): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:64'],
            'table_id' => ['nullable', 'string', 'uuid'],
        ]);

        if ($validated['table_id'] === null && ($validated['label'] ?? null) === null) {
            return back()->withInput()->withErrors(['label' => 'Provide a table label or choose a table to deactivate.']);
        }

        $result = $validated['table_id'] !== null
            ? $tables->deactivate($validated['table_id'])
            : $tables->create($validated['label']);

        return match ($result) {
            'created', 'deactivated' => redirect('/admin/settings')->with('status', 'Table configuration updated.'),
            'duplicate_label' => back()->withInput()->withErrors(['label' => 'That table label is already in use.']),
            'already_inactive' => back()->withErrors(['table_id' => 'That table is already inactive.']),
            'not_found' => back()->withErrors(['table_id' => 'Unknown table.']),
            'invalid_input' => back()->withInput()->withErrors(['label' => 'Check the table label and try again.']),
            default => back()->withInput()->withErrors(['label' => 'Table update failed. Private details withheld.']),
        };
    }

    public function storeReceipt(Request $request, HotelSettingsEditor $editor): RedirectResponse
    {
        $validated = $request->validate([
            'receipt_header' => ['nullable', 'string', 'max:150'],
            'receipt_footer' => ['nullable', 'string', 'max:150'],
        ]);
        $expected = ResourceVersion::fromIfMatch($request->header('If-Match'));
        $result = $editor->update($expected, [
            'receipt_header' => $validated['receipt_header'] ?? null,
            'receipt_footer' => $validated['receipt_footer'] ?? null,
        ]);

        return match ($result) {
            'updated' => redirect('/admin/settings')->with('status', 'Receipt identity updated.'),
            'stale' => redirect('/admin/settings')->with('status', 'Settings changed elsewhere. Reload and try again.'),
            'invalid_input' => back()->withInput()->withErrors(['receipt_header' => 'Check the receipt identity and try again.']),
            default => back()->withInput()->withErrors(['receipt_header' => 'Receipt update failed. Private details withheld.']),
        };
    }

    public function store(Request $request, HotelSettingsEditor $editor): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'timezone' => ['required', 'string', 'in:'.implode(',', HotelSettingsEditor::ALLOWED_TIMEZONES)],
            'business_day_cutoff' => ['nullable', 'string', 'date_format:H:i'],
        ]);

        $expected = ResourceVersion::fromIfMatch($request->header('If-Match'));
        $result = $editor->update($expected, [
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'business_day_cutoff' => $validated['business_day_cutoff'] ?: null,
        ]);

        return match ($result) {
            'updated' => redirect('/admin/settings')->with('status', 'Settings updated.'),
            'stale' => redirect('/admin/settings')->with('status', 'Settings changed elsewhere. Reload and try again.'),
            'invalid_input' => back()->withInput()->withErrors(['name' => 'Check the settings and try again.']),
            default => back()->withInput()->withErrors(['name' => 'Settings update failed. Private details withheld.']),
        };
    }
}
