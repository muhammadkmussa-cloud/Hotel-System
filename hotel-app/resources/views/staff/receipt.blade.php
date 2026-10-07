@extends('layouts.staff', ['title' => 'Receipt '.$r['number']])
@section('mainClass', 'narrow')
@section('content')
<div class="no-print">
    <p class="muted small">@if($visitId)<a href="/staff/visits/{{ $visitId }}">← Back to table</a> · @endif<a href="/staff/cashier">Cashier</a></p>
    <h1>Receipt {{ $r['number'] }}</h1>
    <div class="btn-row">
        <button onclick="window.print()">Print here</button>
        <form method="post" action="/staff/receipts/{{ $checkoutId }}/reprint" class="inline-form">@csrf<button class="btn-secondary">Send copy to receipt printer</button></form>
    </div>
    <p class="small muted">Printer jobs: @forelse($jobs as $j) <span class="pill">{{ $j->is_copy ? 'copy' : 'original' }}: {{ $j->state }}</span> @empty none @endforelse</p>
</div>
<pre class="receipt">{{ $text }}</pre>
<section class="card no-print"><h2>Payments</h2>
    @forelse ($payments as $p)
        <p><b>{{ strtoupper($p->method) }}</b> {{ \App\Domain\Money::format((int) $p->amount_minor) }} @if($p->reference)· {{ $p->reference }}@endif
            @if ((int) $p->refunded_minor > 0)· refunded {{ \App\Domain\Money::format((int) $p->refunded_minor) }}@endif
            <span class="pill">{{ $p->state }}</span></p>
    @empty <p class="muted">No payments recorded.</p>
    @endforelse
    @if (count($payments) && \App\Security\Staff::can(request(), 'refunds.request'))
        <p><a class="btn-secondary" href="/staff/refunds?receipt={{ urlencode($r['number']) }}">Request a refund…</a></p>
    @endif
</section>
@endsection
