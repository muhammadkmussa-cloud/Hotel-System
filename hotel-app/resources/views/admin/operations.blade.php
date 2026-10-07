@extends('layouts.staff', ['title' => 'Operations', 'live' => 'staff'])
@section('content')
<div class="page-head"><h1>Operations</h1>
<form method="post" action="/admin/operations/jobs/run">@csrf<button class="btn-secondary">Run background jobs now</button></form></div>
@php
    $hb = function ($name) use ($heartbeats) { $h = $heartbeats[$name] ?? null; if (! $h) return ['never', 'pill-danger']; $age = time() - strtotime($h->last_seen_at.' UTC'); return [$age < 120 ? 'seen '.$age.'s ago' : \App\Domain\Hotel::localTime($h->last_seen_at, 'd M H:i'), $age < 120 ? 'pill-ok' : 'pill-warn']; };
@endphp
<div class="stats">
    <div class="stat"><b>{{ $mpesaMode }}</b><span>M-PESA mode @if($mpesaMode === 'simulator')(no real money)@endif</span></div>
    <div class="stat"><b>{{ $fiscalProvider }}</b><span>Fiscal provider @if($fiscalProvider === 'simulator')(not tax invoices)@endif</span></div>
    <div class="stat"><b><span class="pill {{ $hb('print_bridge')[1] }}">{{ $hb('print_bridge')[0] }}</span></b><span>Print bridge {{ $bridgeConfigured ? '' : '(token not configured)' }}</span></div>
    <div class="stat"><b><span class="pill {{ $hb('job_runner')[1] }}">{{ $hb('job_runner')[0] }}</span></b><span>Job runner</span></div>
</div>

@if (count($attention))
<div class="notice notice-warn">{{ count($attention) }} M-PESA attempt(s) have an unknown result. Resolve them on the <a href="/staff/cashier">Cashier</a> screen.</div>
@endif

<section class="card"><h2>Print jobs</h2>
<div class="table-wrap"><table class="data"><thead><tr><th>Created</th><th>Kind</th><th>State</th><th>Attempts</th><th>Error</th><th></th></tr></thead><tbody>
@forelse ($printJobs as $j)
<tr><td>{{ \App\Domain\Hotel::localTime($j->created_at, 'd M H:i') }}</td><td>{{ str_replace('_', ' ', $j->kind) }} @if($j->is_copy)<span class="pill">copy</span>@endif</td>
<td><span class="pill {{ $j->state === 'sent' ? 'pill-ok' : (in_array($j->state, ['failed', 'unknown']) ? 'pill-danger' : '') }}">{{ $j->state }}</span>@if($j->simulated) <span class="small muted">simulated</span>@endif</td>
<td>{{ $j->attempts }}</td><td class="small">{{ $j->last_error }}</td>
<td>@if($canPrint)
    @if (in_array($j->state, ['unknown', 'failed']))
        <form method="post" action="/admin/print-jobs/{{ $j->id }}/mark" class="inline-form">@csrf<button name="outcome" value="printed" class="btn-small btn-secondary">It printed</button><button name="outcome" value="not_printed" class="btn-small btn-secondary">Didn't print</button></form>
    @endif
    <form method="post" action="/admin/print-jobs/{{ $j->id }}/reprint" class="inline-form">@csrf<button class="btn-small">Reprint (copy)</button></form>
@endif</td></tr>
@empty<tr><td colspan="6" class="muted">No print jobs yet.</td></tr>@endforelse
</tbody></table></div>
<p class="small muted">A print bridge on the hotel network leases jobs from <code>/bridge/v1/print-jobs/lease</code> with its destination-scoped bridge token and reports results. Reprints are always marked COPY.</p>
</section>

<section class="card"><h2>Fiscal documents</h2>
<div class="table-wrap"><table class="data"><thead><tr><th>Created</th><th>Kind</th><th class="num">Total</th><th>State</th><th>Number</th><th>Error</th><th></th></tr></thead><tbody>
@forelse ($fiscal as $f)
<tr><td>{{ \App\Domain\Hotel::localTime($f->created_at, 'd M H:i') }}</td><td>{{ str_replace('_', ' ', $f->kind) }}</td><td class="num">{{ \App\Domain\Money::format((int) $f->total_minor) }}</td>
<td><span class="pill">{{ $f->state }}</span></td><td>{{ $f->provider_document_number }}</td><td class="small">{{ $f->last_error }}</td>
<td>@if($canFiscal && in_array($f->state, ['failed', 'uncertain', 'pending']))<form method="post" action="/admin/fiscal/{{ $f->id }}/retry">@csrf<button class="btn-small">Retry</button></form>@endif</td></tr>
@empty<tr><td colspan="7" class="muted">None yet.</td></tr>@endforelse
</tbody></table></div>
</section>

<div class="grid-2">
<section class="card"><h2>Background jobs</h2>
<div class="table-wrap"><table class="data"><thead><tr><th>Type</th><th>State</th><th>Tries</th><th>Error</th></tr></thead><tbody>
@forelse ($jobs as $j)<tr><td>{{ $j->type }}</td><td>{{ $j->state }}</td><td>{{ $j->attempts }}</td><td class="small">{{ $j->last_error }}</td></tr>
@empty<tr><td colspan="4" class="muted">No jobs.</td></tr>@endforelse
</tbody></table></div>
<p class="small muted">Schedule <code>php artisan hotel:run-jobs</code> every minute (cron) in production. Jobs also run opportunistically on traffic.</p>
</section>
<section class="card"><h2>Backups</h2>
@if ($canBackup)<form method="post" action="/admin/backups">@csrf<button>Create backup now</button></form>@endif
<ul>
@forelse ($backups as $b)<li>@if($canBackup)<a href="/admin/backups/{{ $b['name'] }}">{{ $b['name'] }}</a>@else{{ $b['name'] }}@endif — {{ number_format($b['bytes'] / 1024, 1) }} KB · {{ date('d M Y H:i', $b['created']) }}</li>
@empty<li class="muted">No backups yet. Schedule <code>php artisan hotel:backup</code> daily.</li>@endforelse
</ul>
<p class="small muted">Backups contain business data (not staff passwords in clear). Store downloaded copies securely off the server.</p>
</section>
</div>

@if ($canSettings)
<section class="card"><h2>Tax and kiosk payment settings</h2>
<form method="post" action="/admin/settings/commerce">@csrf
<div class="form-grid">
    <div class="field"><label for="tax_rate">Tax rate included in prices (%)</label><input id="tax_rate" name="tax_rate" inputmode="decimal" value="{{ $settings->tax_rate_basis_points !== null ? rtrim(rtrim(number_format($settings->tax_rate_basis_points / 100, 2, '.', ''), '0'), '.') : '' }}" placeholder="Blank = not configured">
        <p class="hint">Only enter a rate your accountant has confirmed. Leave blank to print “Tax: not configured”.</p></div>
    <div class="field"><label for="tax_label">Tax label</label><input id="tax_label" name="tax_label" value="{{ $settings->tax_label }}" placeholder="VAT" maxlength="40"></div>
    <div class="field"><label for="kra_pin">KRA PIN (printed on receipts)</label><input id="kra_pin" name="kra_pin" value="{{ $settings->kra_pin }}" maxlength="20"></div>
    <div class="field"><label for="kpm">Kiosk payment window (minutes)</label><input id="kpm" name="kiosk_payment_minutes" type="number" min="2" max="60" value="{{ $settings->kiosk_payment_minutes }}"></div>
</div>
<button>Save</button>
</form>
</section>
@endif
@endsection
