@extends('layouts.staff', ['title' => 'Settings'])
@section('content')
<div class="page-head"><h1>Hotel settings</h1>
    @if ($hotel && $hotel->test_mode)<span class="pill pill-warn">TEST MODE</span>@endif
</div>

<div class="grid-2">
<section class="card">
    <h2>Hotel</h2>
    <form method="post" action="/admin/settings">@csrf
        <input type="hidden" name="expected_version" value="{{ $settings['resource_version'] }}">
        <div class="field"><label for="name">Hotel name</label><input id="name" name="name" maxlength="150" required value="{{ old('name', $settings['name']) }}"></div>
        <div class="field"><label for="timezone">Time zone</label>
            <select id="timezone" name="timezone">@foreach ($timezones as $tz)<option value="{{ $tz }}" @selected(old('timezone', $settings['timezone']) === $tz)>{{ $tz }}</option>@endforeach</select></div>
        <div class="field"><label for="cutoff">Business day ends at</label><input id="cutoff" name="business_day_cutoff" type="time" value="{{ old('business_day_cutoff', $settings['business_day_cutoff'] ? substr($settings['business_day_cutoff'], 0, 5) : '') }}">
            <p class="hint">Sales after midnight but before this time count towards the previous day’s reports.</p></div>
        <button>Save</button>
    </form>
</section>

<section class="card">
    <h2>Receipt identity</h2>
    <form method="post" action="/admin/settings/receipt">@csrf
        <input type="hidden" name="expected_version" value="{{ $settings['resource_version'] }}">
        <div class="field"><label for="rh">Header line</label><input id="rh" name="receipt_header" maxlength="150" value="{{ old('receipt_header', $settings['receipt_header']) }}" placeholder="e.g. P.O. Box 123, Kisii"></div>
        <div class="field"><label for="rf">Footer line</label><input id="rf" name="receipt_footer" maxlength="150" value="{{ old('receipt_footer', $settings['receipt_footer']) }}" placeholder="e.g. Thank you for dining with us"></div>
        <button>Save</button>
    </form>
</section>
</div>

@if ($hotel)
<section class="card"><h2>Tax and kiosk payment</h2>
<form method="post" action="/admin/settings/commerce">@csrf
<div class="form-grid">
    <div class="field"><label for="tax_rate">Tax rate included in prices (%)</label><input id="tax_rate" name="tax_rate" inputmode="decimal" value="{{ $hotel->tax_rate_basis_points !== null ? rtrim(rtrim(number_format($hotel->tax_rate_basis_points / 100, 2, '.', ''), '0'), '.') : '' }}" placeholder="Blank = not configured">
        <p class="hint">Only enter a rate your accountant has confirmed. Blank prints “Tax: not configured”.</p></div>
    <div class="field"><label for="tax_label">Tax label</label><input id="tax_label" name="tax_label" value="{{ $hotel->tax_label }}" placeholder="VAT" maxlength="40"></div>
    <div class="field"><label for="kra_pin">KRA PIN (printed on receipts)</label><input id="kra_pin" name="kra_pin" value="{{ $hotel->kra_pin }}" maxlength="20"></div>
    <div class="field"><label for="kpm">Kiosk payment window (minutes)</label><input id="kpm" name="kiosk_payment_minutes" type="number" min="2" max="60" value="{{ $hotel->kiosk_payment_minutes ?? 10 }}"></div>
</div>
<button>Save</button>
</form>
</section>
@endif

<div class="grid-2">
<section class="card">
    <h2>Tables</h2>
    <ul class="plain-list">
    @foreach ($tables as $t)
        <li class="row-between"><span>{{ $t->label }} @unless($t->active)<span class="pill pill-muted">inactive</span>@endunless</span>
            @if ($t->active)
            <form method="post" action="/admin/settings/tables" class="inline-form">@csrf<input type="hidden" name="table_id" value="{{ $t->id }}"><button class="btn-small btn-secondary">Deactivate</button></form>
            @endif
        </li>
    @endforeach
    </ul>
    <form method="post" action="/admin/settings/tables" class="inline-form">@csrf
        <label for="tlabel" class="visually-hidden">New table label</label><input id="tlabel" name="label" maxlength="64" placeholder="e.g. Table 9 or Terrace 2" required>
        <button>Add table</button>
    </form>
</section>

<section class="card">
    <h2>Kitchen and bar stations</h2>
    <ul class="plain-list">
    @foreach ($stations as $s)
        <li class="row-between"><span>{{ $s->name }} <span class="pill">{{ $s->kind }}</span> @unless($s->active)<span class="pill pill-muted">inactive</span>@endunless</span>
            @if ($s->active)
            <form method="post" action="/admin/settings/stations" class="inline-form">@csrf<input type="hidden" name="station_id" value="{{ $s->id }}"><button class="btn-small btn-secondary">Deactivate</button></form>
            @endif
        </li>
    @endforeach
    </ul>
    <form method="post" action="/admin/settings/stations" class="inline-form">@csrf
        <label for="sname" class="visually-hidden">Station name</label><input id="sname" name="name" maxlength="64" placeholder="Station name" required>
        <label for="skind" class="visually-hidden">Kind</label><select id="skind" name="kind"><option value="kitchen">Kitchen</option><option value="bar">Bar</option></select>
        <button>Add station</button>
    </form>
</section>
</div>

<section class="card">
    <h2>Receipt printer destinations</h2>
    <p class="muted small">The print bridge runs on the hotel network and relays jobs to the physical printer. Destinations must be HTTPS URLs on an allowlisted bridge host.</p>
    <ul class="plain-list">
    @forelse ($printers as $p)
        <li class="row-between"><span>{{ $p->name }} — <code>{{ $p->destination }}</code> @unless($p->active)<span class="pill pill-muted">inactive</span>@endunless</span>
            @if ($p->active)
            <form method="post" action="/admin/settings/printers" class="inline-form">@csrf<input type="hidden" name="printer_id" value="{{ $p->id }}"><button class="btn-small btn-secondary">Deactivate</button></form>
            @endif
        </li>
    @empty <li class="muted">No printers configured — receipts can still be printed from the browser.</li>
    @endforelse
    </ul>
    <form method="post" action="/admin/settings/printers" class="inline-form">@csrf
        <label for="pname" class="visually-hidden">Printer name</label><input id="pname" name="name" maxlength="64" placeholder="Front desk printer" required>
        <label for="pdest" class="visually-hidden">Destination</label><input id="pdest" name="destination" maxlength="255" placeholder="https://bridge.local/printers/1" required>
        <button>Add printer</button>
    </form>
</section>

<section class="card">
    <h2>Integrations</h2>
    <ul class="plain-list">
        <li>M-PESA: <b>{{ config('services.mpesa.mode') === 'live' ? 'Live (Daraja)' : 'Simulator — no real money moves' }}</b></li>
        <li>Fiscal (eTIMS): <b>{{ $integrations['fiscal'] ? 'Enabled' : 'Simulated — receipts are not tax invoices' }}</b></li>
        <li>Print bridge: <b>{{ $integrations['print_bridge'] ? 'Hosts allowlisted' : 'Not configured' }}</b></li>
    </ul>
    <p class="hint">Credentials are set in the server’s private <code>.env</code> file, never in this screen.</p>
</section>
@endsection
