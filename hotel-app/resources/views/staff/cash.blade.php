@extends('layouts.staff', ['title' => 'Cash'])
@section('content')
<div class="page-head"><h1>Cash</h1></div>
<div class="grid-2">
<section class="card"><h2>Cash you are holding</h2>
    <p><b style="font-size:1.5rem">{{ \App\Domain\Money::format($mine['amountMinor']) }}</b> from {{ $mine['count'] }} payment(s)</p>
    @if ($myPending)
        <div class="notice notice-info">Handover of {{ \App\Domain\Money::format((int) $myPending->declared_minor) }} is waiting for a cashier to count.</div>
    @elseif ($mine['amountMinor'] > 0)
        <form method="post" action="/staff/cash/handovers">@csrf
            <div class="field"><label for="hn">Note (optional)</label><input id="hn" name="note" maxlength="300"></div>
            <button>Hand over to cashier</button>
        </form>
    @endif
</section>

@if ($canReconcile)
<section class="card"><h2>Drawer</h2>
    @if ($drawer)
        <dl class="facts">
            <dt>Opened</dt><dd>{{ \App\Domain\Hotel::localTime($drawer->opened_at, 'd M H:i') }}</dd>
            <dt>Float</dt><dd>{{ \App\Domain\Money::format($expected['float']) }}</dd>
            <dt>Cash sales at drawer</dt><dd>{{ \App\Domain\Money::format($expected['sales']) }}</dd>
            <dt>Handovers counted</dt><dd>{{ \App\Domain\Money::format($expected['handovers']) }}</dd>
            <dt>Cash refunds</dt><dd>− {{ \App\Domain\Money::format($expected['refunds']) }}</dd>
            <dt><b>Expected</b></dt><dd><b>{{ $expected['expectedLabel'] }}</b></dd>
        </dl>
        <form method="post" action="/staff/cash/drawers/{{ $drawer->id }}/close">@csrf
            <div class="form-grid">
                <div class="field"><label for="counted">Counted cash</label><input id="counted" name="counted" inputmode="decimal" required></div>
                <div class="field"><label for="dnote">Note (required if different)</label><input id="dnote" name="note" maxlength="300"></div>
            </div>
            <button>Close drawer</button>
        </form>
    @else
        <form method="post" action="/staff/cash/drawers">@csrf
            <div class="field"><label for="float">Opening float</label><input id="float" name="float" inputmode="decimal" required value="0"></div>
            <button>Open drawer</button>
        </form>
    @endif
</section>
@endif
</div>

@if ($canReconcile)
<section class="card"><h2>Handovers to count</h2>
    @forelse ($pending as $h)
        <form method="post" action="/staff/cash/handovers/{{ $h->id }}/decide" class="inline-form" style="display:flex;padding:.5rem 0;border-bottom:1px solid var(--color-border)">@csrf
            <span><b>{{ $h->waiter_name }}</b> declares {{ \App\Domain\Money::format((int) $h->declared_minor) }} @if($h->note) “{{ $h->note }}”@endif</span>
            <input name="counted" inputmode="decimal" placeholder="Counted" value="{{ number_format($h->declared_minor / 100, 2, '.', '') }}">
            <input name="note" placeholder="Note if different" maxlength="300">
            <button name="decision" value="accept" class="btn-small">Accept</button>
            <button name="decision" value="reject" class="btn-small btn-secondary">Reject</button>
        </form>
    @empty <p class="muted">No handovers waiting.</p>
    @endforelse
    <h3>Who is holding cash</h3>
    @forelse ($custodians as $c)<p>{{ $c['name'] }}: <b>{{ $c['amount'] }}</b> ({{ $c['count'] }})</p>@empty<p class="muted">All recorded cash is in the drawer.</p>@endforelse
    <h3>Recent drawer sessions</h3>
    <div class="table-wrap"><table class="data"><thead><tr><th>Closed</th><th class="num">Expected</th><th class="num">Counted</th><th class="num">Variance</th><th>Note</th></tr></thead><tbody>
    @forelse ($history as $d)<tr><td>{{ \App\Domain\Hotel::localTime($d->closed_at, 'd M H:i') }}</td><td class="num">{{ \App\Domain\Money::format((int) $d->expected_minor) }}</td><td class="num">{{ \App\Domain\Money::format((int) $d->counted_minor) }}</td><td class="num">{{ \App\Domain\Money::format((int) $d->variance_minor) }}</td><td>{{ $d->note }}</td></tr>
    @empty<tr><td colspan="5" class="muted">None yet.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endif
@endsection
