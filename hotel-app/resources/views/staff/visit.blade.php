@extends('layouts.staff', ['title' => 'Table '.$visit['tableLabel'], 'live' => 'visit:'.$visit['id']])
@section('content')
<div class="page-head">
    <div>
        <p class="muted small"><a href="/staff/tables">← Tables</a></p>
        <h1>{{ $visit['tableLabel'] }}</h1>
        <p class="muted">Opened {{ \App\Domain\Hotel::localTime($visit['openedAt'], 'H:i') }} · Waiter: {{ $visit['ownerWaiterName'] ?? 'unassigned' }} ·
            @if ($visit['state'] === 'open')<span class="pill pill-ok">Open</span>@else<span class="pill">Closed</span>@endif</p>
    </div>
    @if ($visit['state'] === 'open')
        <form method="post" action="/staff/visits/{{ $visit['id'] }}/guests" class="inline-form">@csrf
            <label class="visually-hidden" for="guest-name">Guest name (optional)</label>
            <input id="guest-name" name="name" type="text" maxlength="150" placeholder="Name (optional)" autocomplete="off">
            <button type="submit">Add guest</button>
        </form>
    @endif
</div>

@if (count($requests) > 0)
<section class="card" aria-labelledby="req-h">
    <h2 id="req-h">Requests</h2>
    <ul class="line-list">
        @foreach ($requests as $r)
            <li class="line-row">
                <span>{{ $r['guest'] ?? 'Table' }} — <b>{{ $r['kindLabel'] }}</b>@if($r['note']) “{{ $r['note'] }}”@endif <span class="muted small">{{ $r['at'] }}</span></span>
                <span class="btn-row">
                    @if ($r['state'] === 'open')<form method="post" action="/staff/service-requests/{{ $r['id'] }}/acknowledged" class="inline-form">@csrf<button class="btn-small btn-secondary">On my way</button></form>@endif
                    <form method="post" action="/staff/service-requests/{{ $r['id'] }}/resolved" class="inline-form">@csrf<input name="resolution" placeholder="Outcome (optional)" maxlength="300"><button class="btn-small">Resolve</button></form>
                </span>
            </li>
        @endforeach
    </ul>
</section>
@endif

@if (count($proposals) > 0 && $canAllocate)
<section class="card" aria-labelledby="share-h">
    <h2 id="share-h">Shared-dish requests</h2>
    <ul class="line-list">
        @foreach ($proposals as $p)
            <li class="line-row">
                <span><b>{{ $p['item'] }}</b> ({{ $p['amount'] }}) shared equally by {{ implode(', ', $p['guests']) }}</span>
                <span class="btn-row">
                    <form method="post" action="/staff/share-proposals/{{ $p['id'] }}/decide" class="inline-form">@csrf<input type="hidden" name="decision" value="confirm"><button class="btn-small">Confirm split</button></form>
                    <form method="post" action="/staff/share-proposals/{{ $p['id'] }}/decide" class="inline-form">@csrf<input type="hidden" name="decision" value="reject"><button class="btn-small btn-secondary">Reject</button></form>
                </span>
            </li>
        @endforeach
    </ul>
</section>
@endif

<h2>Guests and bills</h2>
@if (count($guests) === 0)
    <div class="notice notice-info">No guests yet. Add a guest, then bind a paired tablet to them.</div>
@endif
<div class="grid-2">
@foreach ($guests as $guest)
    @php $bill = $bills[$guest['id']]['bill'] ?? null; @endphp
    <section class="card" aria-labelledby="g-{{ $guest['id'] }}">
        <div class="card-head">
            <h3 id="g-{{ $guest['id'] }}">{{ $guest['label'] }}@if ($guest['name']) — {{ $guest['name'] }}@endif</h3>
            <span class="num"><b>{{ $bill['balance'] ?? 'KSh 0.00' }}</b> due</span>
        </div>
        @if ($bill && count($bill['lines']) > 0)
            <ul class="line-list">
                @foreach ($bill['lines'] as $line)
                    <li>
                        <div class="line-row">
                            <span>{{ $line['quantity'] }} × {{ $line['name'] }}
                                @if ($line['shared'])<span class="pill pill-info">share 1/{{ $line['shareCount'] }}</span>@endif
                                @if ($line['discounted'])<span class="pill pill-info">discounted</span>@endif
                                @if ($line['state'] === 'paid')<span class="pill pill-ok">paid</span>@elseif ($line['state'] === 'frozen')<span class="pill pill-warn">in checkout</span>@elseif ($line['state'] === 'pending')<span class="pill pill-warn">not yet confirmed</span>@endif
                            </span>
                            <span class="num">{{ $line['amount'] }}</span>
                        </div>
                        @if (count($line['removed']))<div class="mods">No {{ implode(', no ', $line['removed']) }}</div>@endif
                        @if (count($line['extras']))<div class="mods add">+ {{ implode(', + ', $line['extras']) }}</div>@endif
                        @if ($line['state'] === 'open' && $visit['state'] === 'open' && ($canAllocate || $canAdjust))
                            <details class="disclosure"><summary>Adjust</summary>
                                @if ($canAllocate)
                                <form method="post" action="/staff/charges/{{ $line['chargeId'] }}/split" class="field">@csrf
                                    <fieldset style="border:0;padding:0;margin:0"><legend class="small"><b>Share or move this dish</b> (equal split)</legend>
                                    @foreach ($guests as $g2)
                                        <label class="check"><input type="checkbox" name="guest_ids[]" value="{{ $g2['id'] }}" @checked($g2['id'] === $guest['id'])> {{ $g2['label'] }}</label>
                                    @endforeach
                                    </fieldset>
                                    <button class="btn-small btn-secondary">Apply split</button>
                                </form>
                                @endif
                                @if ($canAdjust)
                                <form method="post" action="/staff/allocations/{{ $line['allocationId'] }}/discount" class="field">@csrf
                                    <div class="form-grid">
                                        <div><label for="d-{{ $line['allocationId'] }}">Discount (KSh)</label><input id="d-{{ $line['allocationId'] }}" name="amount" inputmode="decimal" required></div>
                                        <div><label for="dr-{{ $line['allocationId'] }}">Reason</label><input id="dr-{{ $line['allocationId'] }}" name="reason" maxlength="300" required></div>
                                    </div>
                                    <button class="btn-small btn-secondary">Apply discount</button>
                                </form>
                                @endif
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="small muted">Paid {{ $bill['paid'] }}@if ($bill['pendingMinor'] > 0) · {{ $bill['pending'] }} awaiting review @endif</p>
        @else
            <p class="muted">Nothing ordered yet.</p>
        @endif

        @if ($visit['state'] === 'open')
        <div class="btn-row">
            @if ($bill && $bill['activeCheckoutId'])
                <a class="btn" href="/staff/checkouts/{{ $bill['activeCheckoutId'] }}">Continue payment</a>
            @elseif ($bill && $bill['openMinor'] > 0 && $canCash)
                <form method="post" action="/staff/guests/{{ $guest['id'] }}/checkout" class="inline-form">@csrf<button>Take payment</button></form>
            @endif
            <a class="btn btn-secondary" href="/staff/guests/{{ $guest['id'] }}/bill" target="_blank" rel="noopener">Print bill</a>
        </div>

        <details class="disclosure"><summary>Tablet ({{ count($guest['devices']) ? $guest['devices'][0]['deviceName'] : 'none' }})</summary>
            @foreach ($guest['devices'] as $binding)
                <div class="btn-row">
                    <span>{{ $binding['deviceName'] }}</span>
                    <form method="post" action="/staff/guest-bindings/{{ $binding['id'] }}/revoke" class="inline-form">@csrf<input type="hidden" name="visit_id" value="{{ $visit['id'] }}"><button class="btn-small btn-secondary">Unbind</button></form>
                    <form method="post" action="/staff/guest-bindings/{{ $binding['id'] }}/replace" class="inline-form">@csrf<input type="hidden" name="visit_id" value="{{ $visit['id'] }}"><button class="btn-small btn-secondary">Replace tablet</button></form>
                </div>
            @endforeach
            @if (count($bindableSessions) === 0)
                <p class="small muted">No paired tablets are free. Pair one at <a href="/admin/devices">Devices</a>.</p>
            @else
                <form method="post" action="/staff/guest-bindings" class="inline-form">@csrf
                    <input type="hidden" name="guest_id" value="{{ $guest['id'] }}">
                    <label class="visually-hidden" for="bind-{{ $guest['id'] }}">Tablet</label>
                    <select id="bind-{{ $guest['id'] }}" name="device_session_id" required>
                        @foreach ($bindableSessions as $session)<option value="{{ $session['sessionId'] }}">{{ $session['deviceName'] }}</option>@endforeach
                    </select>
                    <button class="btn-small">Give tablet to {{ $guest['label'] }}</button>
                </form>
            @endif
        </details>
        @endif
    </section>
@endforeach
</div>

<h2>Orders</h2>
@if (count($orders) === 0)
    <p class="muted">No orders yet.</p>
@else
<div class="table-wrap"><table class="data">
    <thead><tr><th>Ref</th><th>Guest</th><th>Items</th><th>Status</th><th class="num">Total</th></tr></thead>
    <tbody>
    @foreach ($orders as $o)
        <tr>
            <td class="nowrap">{{ $o['reference'] }}<br><span class="muted small">{{ $o['submittedAt'] }}</span></td>
            <td>{{ $o['guestLabel'] }}</td>
            <td>
                @foreach ($o['items'] as $item)
                    <div @if($item['cancelled']) style="text-decoration:line-through" class="muted" @endif>
                        {{ $item['quantity'] }} × {{ $item['name'] }} <span class="pill">{{ str_replace('_', ' ', $item['status']) }}</span>
                        @if (count($item['removed']))<span class="mods">no {{ implode(', no ', $item['removed']) }}</span>@endif
                        @if (count($item['extras']))<span class="mods add">+ {{ implode(', + ', $item['extras']) }}</span>@endif
                        @if ($item['note'])<span class="small">“{{ $item['note'] }}”</span>@endif
                        @if ($canAdjust && ! $item['cancelled'] && $visit['state'] === 'open')
                            <details class="disclosure"><summary class="small">Cancel item</summary>
                                <form method="post" action="/staff/order-items/{{ $item['id'] }}/cancel">@csrf
                                    <div class="field"><label for="c-{{ $item['id'] }}">Reason</label><input id="c-{{ $item['id'] }}" name="reason" maxlength="300" required></div>
                                    <label class="check"><input type="checkbox" name="return_portion" value="1"> Return the portion to stock (not yet cooked)</label>
                                    <button class="btn-small btn-danger">Cancel {{ $item['name'] }}</button>
                                </form>
                            </details>
                        @endif
                    </div>
                @endforeach
                @if ($o['hasAllergyNote'])<div class="notice notice-warn small">Guest note: {{ $o['allergyNote'] }}@if($o['reviewNote'])<br>Staff: {{ $o['reviewNote'] }}@endif</div>@endif
            </td>
            <td>{{ $o['statusLabel'] }}</td>
            <td class="num">{{ $o['total'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table></div>
@endif

@if ($canTransfer && $visit['state'] === 'open')
<details class="disclosure"><summary>Move table or change waiter (manager)</summary>
    <form method="post" action="/staff/visits/{{ $visit['id'] }}/transfers">@csrf
        <input type="hidden" name="expected_version" value="{{ $visit['version'] }}">
        <div class="form-grid">
            <div class="field"><label for="transfer-table">Table</label>
                <select id="transfer-table" name="table_id"><option value="">Keep current table</option>
                    @foreach ($transferTables as $table)@if ($table['tableId'] !== $visit['tableId'])<option value="{{ $table['tableId'] }}">{{ $table['label'] }}</option>@endif @endforeach
                </select></div>
            <div class="field"><label for="transfer-waiter">Waiter</label>
                <select id="transfer-waiter" name="waiter_id"><option value="">Unassigned</option>
                    @foreach ($waiters as $waiter)<option value="{{ $waiter['id'] }}" @selected($waiter['id'] === $visit['ownerWaiterId'])>{{ $waiter['name'] }}</option>@endforeach
                </select></div>
        </div>
        <button type="submit" class="btn-secondary">Transfer</button>
    </form>
</details>
@endif

@if ($visit['state'] === 'open')
<section class="card" aria-labelledby="close-h">
    <h2 id="close-h">Close visit</h2>
    @if (count($blockers) > 0)
        <div class="notice notice-warn"><strong>Not ready to close:</strong><ul>@foreach ($blockers as $b)<li>{{ $b }}</li>@endforeach</ul></div>
    @else
        <p>Everything is paid and served. Closing ends every guest's tablet access.</p>
    @endif
    <form method="post" action="/staff/visits/{{ $visit['id'] }}/close">@csrf
        <input type="hidden" name="expected_version" value="{{ $visit['version'] }}">
        <button type="submit" @disabled(count($blockers) > 0)>Close visit</button>
    </form>
</section>
@endif
@endsection
