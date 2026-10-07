@extends('layouts.staff', ['title' => 'Take payment'])
@section('mainClass', 'narrow')
@section('content')
<p class="muted small">@if($owner)<a href="/staff/visits/{{ $owner->visit_id }}">← {{ $owner->table_label }}</a>@else<a href="/staff/cashier">← Cashier</a>@endif</p>
<h1>Take payment</h1>
<p>{{ $owner ? $owner->table_label.' · '.$owner->label : 'Kiosk order '.$kiosk?->reference_code }}</p>
<div class="card">
    <ul class="line-list">
        @foreach ($lines as $l)<li class="line-row"><span>{{ $l->quantity }} × {{ $l->meal_name }}</span><span class="num">{{ \App\Domain\Money::format((int) $l->amount_minor) }}</span></li>@endforeach
    </ul>
    <dl class="facts">
        <dt>Total</dt><dd class="num" style="text-align:left"><b>{{ $c['amount'] }}</b></dd>
        @if ($c['paidMinor'] > 0)<dt>Paid so far</dt><dd>{{ $c['paid'] }}</dd>@endif
        <dt>To pay now</dt><dd><b style="font-size:1.4rem">{{ $c['remaining'] }}</b></dd>
    </dl>
    @foreach ($c['payments'] as $p)<p class="small muted">{{ ucfirst($p['method']) }} {{ $p['amount'] }} {{ $p['reference'] }}</p>@endforeach
</div>

@if ($c['latestAttempt'] && $c['latestAttempt']['state'] === 'pending')
    <div class="notice notice-info" data-attempt-poll="/staff/checkouts/{{ $c['id'] }}/poll" role="status" aria-live="polite">{{ $c['latestAttempt']['message'] }}</div>
@elseif ($c['latestAttempt'] && in_array($c['latestAttempt']['state'], ['failed', 'cancelled', 'unknown']))
    <div class="notice notice-warn">{{ $c['latestAttempt']['message'] }}</div>
@endif

<details class="disclosure" open><summary>Cash</summary>
    <form method="post" action="/staff/checkouts/{{ $c['id'] }}/cash" data-change-calc>@csrf
        <div class="form-grid">
            <div class="field"><label for="cash-amount">Amount to pay</label><input id="cash-amount" name="amount" inputmode="decimal" value="{{ number_format($c['remainingMinor'] / 100, 2, '.', '') }}"></div>
            <div class="field"><label for="tendered">Cash received</label><input id="tendered" name="tendered" inputmode="decimal" required autocomplete="off"></div>
        </div>
        <p data-change-output class="small" aria-live="polite"></p>
        <button>Record cash</button>
    </form>
</details>
@if ($canCard)
<details class="disclosure"><summary>Card (on the terminal)</summary>
    <p class="small muted">Run the card on the separate card terminal first. This system never takes card numbers.</p>
    <form method="post" action="/staff/checkouts/{{ $c['id'] }}/card">@csrf
        <div class="form-grid">
            <div class="field"><label for="card-amount">Amount charged</label><input id="card-amount" name="amount" inputmode="decimal" value="{{ number_format($c['remainingMinor'] / 100, 2, '.', '') }}"></div>
            <div class="field"><label for="card-ref">Terminal approval / receipt ref</label><input id="card-ref" name="reference" required maxlength="32" autocomplete="off"></div>
        </div>
        <label class="check"><input type="checkbox" name="confirmed" value="1" required> The terminal printed APPROVED for this amount</label>
        <button>Record card payment</button>
    </form>
</details>
@endif
@if ($canMpesa)
<details class="disclosure"><summary>M-PESA (STK push){{ $mpesaMode === 'simulator' ? ' — simulator' : '' }}</summary>
    @if (! $c['mpesaEligible'])
        <p class="notice notice-warn">M-PESA takes whole shillings only. Use cash or card for {{ $c['remaining'] }}.</p>
    @else
        @if ($mpesaMode === 'simulator')<p class="small muted">Simulator: numbers ending 111 fail, 222 cancel, 333 time out; others succeed after a few seconds. No money moves.</p>@endif
        <form method="post" action="/staff/checkouts/{{ $c['id'] }}/mpesa" class="inline-form">@csrf
            <label for="phone" class="visually-hidden">Phone</label><input id="phone" name="phone" inputmode="tel" placeholder="07XX XXX XXX" required maxlength="16">
            <button>Send request for {{ $c['remaining'] }}</button>
        </form>
    @endif
</details>
@endif
@if ($c['paidMinor'] === 0)
<form method="post" action="/staff/checkouts/{{ $c['id'] }}/cancel" style="margin-top:1rem">@csrf<button class="btn-ghost">Cancel checkout</button></form>
@endif
@endsection
