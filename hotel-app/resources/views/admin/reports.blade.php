@extends('layouts.staff', ['title' => 'Reports'])
@php $m = fn ($v) => \App\Domain\Money::format((int) $v); @endphp
@section('content')
<div class="page-head"><h1>Reports</h1>
<form method="get" class="inline-form" style="display:flex">
    <label for="from">From</label><input id="from" type="date" name="from" value="{{ $from }}">
    <label for="to">To</label><input id="to" type="date" name="to" value="{{ $to }}">
    <button class="btn-secondary">Show</button>
</form></div>
@if ($testMode)<div class="notice notice-warn">Test mode is on: these figures include test sales.</div>@endif
<div class="stats">
    <div class="stat"><b>{{ $m($r['gross']) }}</b><span>Gross sales</span></div>
    <div class="stat"><b>{{ $m($r['discounts'] + $r['cancellations']) }}</b><span>Discounts &amp; cancellations</span></div>
    <div class="stat"><b>{{ $m($r['net']) }}</b><span>Net sales</span></div>
    <div class="stat"><b>{{ $m($r['collected']) }}</b><span>Collected</span></div>
    <div class="stat"><b>{{ $m($r['refunds']) }}</b><span>Refunded</span></div>
    <div class="stat"><b>{{ $r['orders'] }}</b><span>Orders ({{ $r['tableOrders'] }} table · {{ $r['kioskOrders'] }} kiosk)</span></div>
</div>
<div class="grid-2">
<section class="card"><h2>Payments by method</h2>
<dl class="facts">
    <dt>Cash</dt><dd>{{ $m($r['payments']['cash']) }}</dd>
    <dt>Card (terminal)</dt><dd>{{ $m($r['payments']['card']) }}</dd>
    <dt>M-PESA</dt><dd>{{ $m($r['payments']['mpesa']) }}</dd>
    <dt>Unapplied M-PESA</dt><dd>{{ $m($r['unapplied']) }}</dd>
    <dt>Tax included</dt><dd>{{ $r['tax'] !== null ? $m($r['tax']) : ($r['taxConfigured'] ? $m(0) : 'Not configured') }}</dd>
    <dt>Drawer variance</dt><dd>{{ $m($r['drawerVariance']) }} over {{ $r['drawerCount'] }} session(s)</dd>
    <dt>Unpaid on open bills (now)</dt><dd>{{ $m($r['openBalances']) }}</dd>
</dl>
<div class="btn-row">
    <a class="btn btn-secondary" href="/admin/reports/export/payments?from={{ $from }}&to={{ $to }}">Download payments CSV</a>
    <a class="btn btn-secondary" href="/admin/reports/export/sales?from={{ $from }}&to={{ $to }}">Download item sales CSV</a>
</div>
</section>
<section class="card"><h2>Top items</h2>
<div class="table-wrap"><table class="data"><thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Gross</th></tr></thead><tbody>
@forelse ($r['top'] as $t)<tr><td>{{ $t['name'] }}</td><td class="num">{{ $t['qty'] }}</td><td class="num">{{ $m($t['gross']) }}</td></tr>
@empty<tr><td colspan="3" class="muted">No sales in this range.</td></tr>@endforelse
</tbody></table></div>
</section>
</div>
@if (count($r['daily']) > 1)
<section class="card"><h2>By day</h2>
<div class="table-wrap"><table class="data"><thead><tr><th>Date</th><th class="num">Lines</th><th class="num">Gross</th></tr></thead><tbody>
@foreach ($r['daily'] as $d)<tr><td>{{ $d['date'] }}</td><td class="num">{{ $d['lines'] }}</td><td class="num">{{ $m($d['gross']) }}</td></tr>@endforeach
</tbody></table></div></section>
@endif
<p class="small muted">Business dates follow the hotel's day cut-off. Figures are calculated from the payment ledger; tax shown is the portion already included in prices.</p>
@endsection
