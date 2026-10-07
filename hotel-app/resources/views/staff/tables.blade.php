@extends('layouts.staff', ['title' => 'Tables', 'live' => 'staff'])
@section('content')
<div class="page-head">
    <h1>Tables</h1>
    <p class="muted">{{ $occupiedCount }} of {{ count($tables) }} tables occupied</p>
</div>

@if (count($requests) > 0)
<section class="card" aria-labelledby="req-h">
    <h2 id="req-h">Guests asking for help</h2>
    <ul class="line-list">
        @foreach ($requests as $r)
            <li class="line-row">
                <span><b>{{ $r['table'] }}</b> · {{ $r['guest'] ?? 'Table' }} — {{ $r['kindLabel'] }}@if($r['note']) <span class="muted">“{{ $r['note'] }}”</span>@endif <span class="muted small">{{ $r['at'] }}</span>
                    @if($r['state'] === 'acknowledged')<span class="pill pill-info">On the way</span>@endif</span>
                <span class="btn-row">
                    @if ($r['state'] === 'open')
                        <form method="post" action="/staff/service-requests/{{ $r['id'] }}/acknowledged" class="inline-form">@csrf<button class="btn-small btn-secondary">On my way</button></form>
                    @endif
                    <form method="post" action="/staff/service-requests/{{ $r['id'] }}/resolved" class="inline-form">@csrf<button class="btn-small">Done</button></form>
                    <a class="btn btn-small btn-ghost" href="/staff/visits/{{ $r['visitId'] }}">Open table</a>
                </span>
            </li>
        @endforeach
    </ul>
</section>
@endif

@if (count($tables) === 0)
    <div class="notice notice-info">No tables are configured yet. An owner can add them in <a href="/admin/settings">Settings</a>.</div>
@else
<div class="table-tiles">
    @foreach ($tables as $table)
        @if ($table['visitId'])
            <a class="table-tile occupied {{ ($table['ready'] || $table['requests'] || $table['held']) ? 'attention' : '' }}" href="/staff/visits/{{ $table['visitId'] }}">
                <b>{{ $table['label'] }}</b>
                <span>{{ $table['guestCount'] }} {{ $table['guestCount'] === 1 ? 'guest' : 'guests' }}</span><br>
                <span class="num">Due {{ \App\Domain\Money::format($table['balanceMinor']) }}</span><br>
                @if ($table['ready'])<span class="pill pill-ok">{{ $table['ready'] }} ready to serve</span>@endif
                @if ($table['requests'])<span class="pill pill-warn">{{ $table['requests'] }} request</span>@endif
                @if ($table['held'])<span class="pill pill-warn">{{ $table['held'] }} in review</span>@endif
            </a>
        @else
            <div class="table-tile">
                <b>{{ $table['label'] }}</b>
                <span class="muted">Free</span>
                <form method="post" action="/staff/visits/open">@csrf
                    <input type="hidden" name="table_id" value="{{ $table['tableId'] }}">
                    <button class="btn-small" type="submit">Seat guests</button>
                </form>
            </div>
        @endif
    @endforeach
</div>
@endif
@endsection
