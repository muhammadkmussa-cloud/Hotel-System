@extends('layouts.staff', ['title' => 'Cashier', 'live' => 'staff'])
@section('content')
<div class="page-head">
    <h1>Cashier</h1>
    <form method="get" action="/staff/cashier" class="inline-form">
        <label for="ref" class="visually-hidden">Kiosk reference</label>
        <input id="ref" name="ref" value="{{ $ref }}" placeholder="Kiosk ref e.g. K7F3QD" maxlength="12" autocomplete="off">
        <button class="btn-secondary">Find</button>
    </form>
</div>
@if (! $drawer)
    <div class="notice notice-warn">No cash drawer is open. Cash you take is held in your custody until handed over. <a href="/staff/cash">Open a drawer</a></div>
@endif
@if ($ref !== '')
    @if ($found)
        <section class="card"><h2>Kiosk {{ $found['reference'] }}</h2>
            <p>{{ $found['total'] }} · <span class="pill">{{ str_replace('_', ' ', $found['state']) }}</span> @if($found['name']) · {{ $found['name'] }}@endif</p>
            @if ($found['state'] === 'pending_payment')
                @if ($found['checkoutId'] && $found['checkoutState'] === 'open')<a class="btn" href="/staff/checkouts/{{ $found['checkoutId'] }}">Continue payment</a>
                @else<form method="post" action="/staff/kiosk-orders/{{ $found['id'] }}/checkout">@csrf<button>Take payment</button></form>@endif
            @endif
        </section>
    @else
        <div class="notice notice-error">No kiosk order with reference “{{ $ref }}”.</div>
    @endif
@endif

@if (count($attention) > 0)
<section class="card"><h2>M-PESA results to confirm</h2>
    <p class="muted">The provider has not confirmed these. Check the M-PESA statement before deciding; never ask the guest to pay twice.</p>
    @foreach ($attention as $a)
        <form method="post" action="/staff/payment-attempts/{{ $a->id }}/resolve" class="inline-form card" style="display:flex">@csrf
            <span>{{ \App\Domain\Money::format((int) $a->amount_minor) }} from {{ $a->phone_masked }} at {{ \App\Domain\Hotel::localTime($a->created_at) }}</span>
            <input name="receipt" placeholder="M-PESA receipt code" maxlength="20">
            <button name="outcome" value="received" class="btn-small">Money received</button>
            <button name="outcome" value="not_received" class="btn-small btn-secondary">Not received</button>
        </form>
    @endforeach
</section>
@endif

<div class="grid-2">
<section class="card"><h2>Table bills with a balance</h2>
    @forelse ($guests as $g)
        <div class="line-row" style="padding:.5rem 0;border-bottom:1px solid var(--color-border)">
            <span><b>{{ $g['table'] }}</b> · {{ $g['label'] }}@if($g['name']) ({{ $g['name'] }})@endif</span>
            <span class="btn-row"><span class="num">{{ $g['bill']['balance'] }}</span>
                @if ($g['bill']['activeCheckoutId'])<a class="btn btn-small" href="/staff/checkouts/{{ $g['bill']['activeCheckoutId'] }}">Continue</a>
                @else<form method="post" action="/staff/guests/{{ $g['id'] }}/checkout" class="inline-form">@csrf<button class="btn-small">Take payment</button></form>@endif
            </span>
        </div>
    @empty <p class="muted">No open balances.</p>
    @endforelse
</section>
<section class="card"><h2>Kiosk orders awaiting payment</h2>
    @forelse ($kiosk as $k)
        <div class="line-row" style="padding:.5rem 0;border-bottom:1px solid var(--color-border)">
            <span><b>{{ $k['reference'] }}</b> {{ $k['name'] }} · {{ $k['route'] === 'mpesa' ? 'M-PESA' : 'Pay at cashier' }}
                @if($k['state'] === 'review_hold')<span class="pill pill-warn">in review</span>@endif
                @if($k['expiresInSeconds'] !== null)<span class="muted small">expires in {{ intdiv($k['expiresInSeconds'], 60) }} min</span>@endif</span>
            <span class="btn-row"><span class="num">{{ $k['total'] }}</span>
                @if ($k['state'] === 'pending_payment')
                    @if ($k['checkoutId'] && $k['checkoutState'] === 'open')<a class="btn btn-small" href="/staff/checkouts/{{ $k['checkoutId'] }}">Continue</a>
                    @else<form method="post" action="/staff/kiosk-orders/{{ $k['id'] }}/checkout" class="inline-form">@csrf<button class="btn-small">Take payment</button></form>@endif
                @endif
            </span>
        </div>
    @empty <p class="muted">None waiting.</p>
    @endforelse
</section>
</div>

<section class="card"><h2>Recent receipts</h2>
    <div class="table-wrap"><table class="data"><thead><tr><th>Receipt</th><th>Paid</th><th class="num">Amount</th><th></th></tr></thead><tbody>
    @forelse ($recent as $c)
        <tr><td>{{ $c->receipt_number }}</td><td>{{ \App\Domain\Hotel::localTime($c->paid_at, 'd M H:i') }}</td><td class="num">{{ \App\Domain\Money::format((int) $c->amount_minor) }}</td><td><a href="/staff/receipts/{{ $c->id }}">View</a></td></tr>
    @empty <tr><td colspan="4" class="muted">No receipts yet today.</td></tr>
    @endforelse
    </tbody></table></div>
</section>
@endsection
