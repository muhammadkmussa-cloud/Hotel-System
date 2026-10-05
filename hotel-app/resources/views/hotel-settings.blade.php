<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <title>{{ config('app.name') }} — Hotel settings</title>
</head>
<body>
    <main id="main">
        <h1>Hotel settings</h1>
        <p>Versioned identity and business-day settings. Stale edits are rejected.</p>

        @if (session('status'))
            <p role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="state" tabindex="-1" data-error-focus>
                <h2>Check these details</h2>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="/admin/settings" data-single-submit>
            @csrf
            <input type="hidden" name="expected_version" value="{{ $settings['resource_version'] }}">
            <div class="field">
                <label for="name">Hotel trading name</label>
                <input id="name" name="name" type="text" maxlength="150" required value="{{ old('name', $settings['name']) }}">
            </div>
            <div class="field">
                <label for="timezone">Timezone</label>
                <select id="timezone" name="timezone" required>
                    @foreach ($timezones as $timezone)
                        <option value="{{ $timezone }}" @selected(old('timezone', $settings['timezone']) === $timezone)>{{ $timezone }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="business_day_cutoff">Business-day cutoff</label>
                <input id="business_day_cutoff" name="business_day_cutoff" type="time" value="{{ old('business_day_cutoff', $settings['business_day_cutoff']) }}">
            </div>
            <button type="submit" data-pending-label="Saving…">Save settings</button>
            <p data-submit-status class="visually-hidden" role="status" aria-live="polite"></p>
        </form>
    <section aria-labelledby="receipt-heading">
        <h2 id="receipt-heading">Receipt identity</h2>
        <form method="post" action="/admin/settings/receipt" data-single-submit>
            @csrf
            <input type="hidden" name="expected_version" value="{{ $settings['resource_version'] }}">
            <div class="field">
                <label for="receipt_header">Receipt header</label>
                <input id="receipt_header" name="receipt_header" type="text" maxlength="150" value="{{ old('receipt_header', $settings['receipt_header']) }}">
            </div>
            <div class="field">
                <label for="receipt_footer">Receipt footer</label>
                <input id="receipt_footer" name="receipt_footer" type="text" maxlength="150" value="{{ old('receipt_footer', $settings['receipt_footer']) }}">
            </div>
            <button type="submit" data-pending-label="Saving…">Save receipt identity</button>
            <p data-submit-status class="visually-hidden" role="status" aria-live="polite"></p>
        </form>
    </section>

    <section aria-labelledby="integrations-heading">
        <h2 id="integrations-heading">Integrations</h2>
        <p>Redacted status only; secrets are never shown.</p>
        <table>
            <caption>Integration status</caption>
            <tbody>
                <tr><th scope="row">M-PESA</th><td>{{ $integrations['mpesa'] ? 'Configured' : 'Not configured' }}</td></tr>
                <tr><th scope="row">Fiscal (eTIMS)</th><td>{{ $integrations['fiscal'] ? 'Configured' : 'Not configured' }}</td></tr>
                <tr><th scope="row">Print bridge</th><td>{{ $integrations['print_bridge'] ? 'Configured' : 'Not configured' }}</td></tr>
            </tbody>
        </table>
    </section>

    <section aria-labelledby="tables-heading">
        <h2 id="tables-heading">Tables</h2>
        @if (count($tables) === 0)
            <p>No tables are configured yet.</p>
        @else
            <table>
                <caption>Configured tables</caption>
                <thead><tr><th scope="col">Label</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                <tbody>
                    @foreach ($tables as $table)
                        <tr>
                            <th scope="row">{{ $table['label'] }}</th>
                            <td>{{ $table['active'] ? 'Active' : 'Inactive' }}</td>
                            <td>
                                @if ($table['active'])
                                    <form method="post" action="/admin/settings/tables">
                                        @csrf
                                        <input type="hidden" name="table_id" value="{{ $table['id'] }}">
                                        <button type="submit">Deactivate</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <form method="post" action="/admin/settings/tables" data-single-submit>
            @csrf
            <div class="field">
                <label for="table_label">New table label</label>
                <input id="table_label" name="label" type="text" maxlength="64" required value="{{ old('label') }}">
            </div>
            <button type="submit" data-pending-label="Adding…">Add table</button>
            <p data-submit-status class="visually-hidden" role="status" aria-live="polite"></p>
        </form>
    </section>
    <section aria-labelledby="stations-heading">
        <h2 id="stations-heading">Stations</h2>
        @if (count($stations) === 0)
            <p>No stations are configured yet.</p>
        @else
            <table>
                <caption>Configured stations</caption>
                <thead><tr><th scope="col">Name</th><th scope="col">Kind</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                <tbody>
                    @foreach ($stations as $station)
                        <tr>
                            <th scope="row">{{ $station['name'] }}</th>
                            <td>{{ $station['kind'] }}</td>
                            <td>{{ $station['active'] ? 'Active' : 'Inactive' }}</td>
                            <td>
                                @if ($station['active'])
                                    <form method="post" action="/admin/settings/stations">
                                        @csrf
                                        <input type="hidden" name="station_id" value="{{ $station['id'] }}">
                                        <button type="submit">Deactivate</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <form method="post" action="/admin/settings/stations" data-single-submit>
            @csrf
            <div class="field">
                <label for="station_name">New station name</label>
                <input id="station_name" name="name" type="text" maxlength="64" required value="{{ old('name') }}">
            </div>
            <div class="field">
                <label for="station_kind">Kind</label>
                <select id="station_kind" name="kind" required>
                    <option value="kitchen" @selected(old('kind') === 'kitchen')>Kitchen</option>
                    <option value="bar" @selected(old('kind') === 'bar')>Bar</option>
                </select>
            </div>
            <button type="submit" data-pending-label="Adding…">Add station</button>
            <p data-submit-status class="visually-hidden" role="status" aria-live="polite"></p>
        </form>
    </section>
    <section aria-labelledby="printers-heading">
        <h2 id="printers-heading">Printer destinations</h2>
        <p>Only HTTPS destinations on an allowlisted bridge host can be registered.</p>
        @if (count($printers) === 0)
            <p>No printer destinations are configured yet.</p>
        @else
            <table>
                <caption>Configured printer destinations</caption>
                <thead><tr><th scope="col">Name</th><th scope="col">Destination</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                <tbody>
                    @foreach ($printers as $printer)
                        <tr>
                            <th scope="row">{{ $printer['name'] }}</th>
                            <td>{{ $printer['destination'] }}</td>
                            <td>{{ $printer['active'] ? 'Active' : 'Inactive' }}</td>
                            <td>
                                @if ($printer['active'])
                                    <form method="post" action="/admin/settings/printers">
                                        @csrf
                                        <input type="hidden" name="printer_id" value="{{ $printer['id'] }}">
                                        <button type="submit">Deactivate</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <form method="post" action="/admin/settings/printers" data-single-submit>
            @csrf
            <div class="field">
                <label for="printer_name">New printer name</label>
                <input id="printer_name" name="name" type="text" maxlength="64" required value="{{ old('name') }}">
            </div>
            <div class="field">
                <label for="printer_destination">Destination (HTTPS)</label>
                <input id="printer_destination" name="destination" type="url" maxlength="255" required value="{{ old('destination') }}">
            </div>
            <button type="submit" data-pending-label="Adding…">Add printer destination</button>
            <p data-submit-status class="visually-hidden" role="status" aria-live="polite"></p>
        </form>
    </section>
    </main>
    <script type="module">
        import { initSingleSubmit } from '/assets/js/lib/single-submit.js';
        initSingleSubmit(document.querySelector('form[data-single-submit]'));
        document.querySelector('[data-error-focus]')?.focus();
    </script>
</body>
</html>
