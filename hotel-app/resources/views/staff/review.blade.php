@extends('layouts.staff', ['title' => 'Order review', 'live' => 'staff'])
@section('content')
<div class="page-head"><h1>Orders waiting for review</h1></div>
<div class="notice notice-info">These orders include an allergy or dietary note. Speak to the kitchen and, where needed, the guest. Approving sends the order to the kitchen exactly as written (or, for kiosk orders, unlocks payment). Never promise a dish is allergen-free.</div>
@forelse ($queue as $o)
    <section class="card">
        <div class="card-head">
            <h2>{{ $o['channel'] === 'table' ? $o['tableLabel'].' · '.$o['guestLabel'] : 'Kiosk '.$o['reference'].($o['guestLabel'] ? ' · '.$o['guestLabel'] : '') }}</h2>
            <span class="muted">{{ $o['submittedAt'] }} · {{ $o['total'] }}</span>
        </div>
        <div class="notice notice-warn"><b>Guest note:</b> {{ $o['allergyNote'] }}</div>
        <ul class="line-list">
            @foreach ($o['items'] as $item)
                <li>{{ $item['quantity'] }} × <b>{{ $item['name'] }}</b>
                    @if (count($item['removed']))<span class="mods">no {{ implode(', no ', $item['removed']) }}</span>@endif
                    @if (count($item['extras']))<span class="mods add">+ {{ implode(', + ', $item['extras']) }}</span>@endif
                    @if ($item['note'])<span>“{{ $item['note'] }}”</span>@endif
                </li>
            @endforeach
        </ul>
        <div class="grid-2">
            <form method="post" action="/staff/review/{{ $o['id'] }}/approve">@csrf
                <input type="hidden" name="version" value="{{ $versions[$o['id']] ?? 1 }}">
                <div class="field"><label for="n-{{ $o['id'] }}">Note for the kitchen (shown on the ticket)</label><textarea id="n-{{ $o['id'] }}" name="note" maxlength="500" placeholder="e.g. Confirmed with guest: no peanuts; prepare on a clean board."></textarea></div>
                <button>Approve and release</button>
            </form>
            <form method="post" action="/staff/review/{{ $o['id'] }}/decline">@csrf
                <div class="field"><label for="r-{{ $o['id'] }}">Reason (tell the guest)</label><textarea id="r-{{ $o['id'] }}" name="reason" maxlength="500" required></textarea></div>
                <button class="btn-danger">Decline order</button>
            </form>
        </div>
    </section>
@empty
    <p class="muted">Nothing to review right now.</p>
@endforelse
@endsection
