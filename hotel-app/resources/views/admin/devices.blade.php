@extends('layouts.staff', ['title' => 'Devices'])
@section('content')
<div class="page-head"><h1>Devices</h1></div>
@if ($p = session('pairing'))
<div class="notice notice-ok" role="status">
    <p>On <b>{{ $p['name'] }}</b>, open <code>{{ url('/device/pair') }}</code> and enter:</p>
    <p style="font-size:2.2rem;font-weight:700;letter-spacing:.15em;font-family:var(--font-mono, monospace)">{{ $p['code'] }}</p>
    <p class="small">The code works once and expires in 15 minutes.</p>
</div>
@endif
<section class="card"><h2>Add a device</h2>
<form method="post" action="/admin/devices/enroll" class="inline-form" style="display:flex;flex-wrap:wrap">@csrf
    <label for="dname" class="visually-hidden">Name</label><input id="dname" name="name" placeholder="e.g. Table tablet 3" required maxlength="64">
    <label for="dmode" class="visually-hidden">Type</label>
    <select id="dmode" name="mode">
        <option value="tablet">Table tablet (guest ordering)</option>
        <option value="kiosk">Self-order kiosk</option>
        <option value="kitchen">Kitchen display</option>
        <option value="collection">Collection display</option>
        <option value="cashier">Cashier terminal</option>
    </select>
    <button>Create and get pairing code</button>
</form>
</section>
<div class="table-wrap card"><table class="data">
<thead><tr><th>Name</th><th>Type</th><th>Status</th><th>Last seen</th><th>Sessions</th><th></th></tr></thead><tbody>
@forelse ($devices as $d)
<tr>
    <td><b>{{ $d->name }}</b></td><td>{{ $d->mode }}</td>
    <td>@if($d->active)<span class="pill pill-ok">active</span>@else<span class="pill pill-danger">revoked</span>@endif</td>
    <td>{{ $d->last_seen_at ? \App\Domain\Hotel::localTime($d->last_seen_at, 'd M H:i') : 'never' }}</td>
    <td>@foreach (($sessions[$d->id] ?? collect()) as $s)
        <form method="post" action="/admin/device-sessions/{{ $s->id }}/revoke" class="inline-form">@csrf<span class="small">since {{ \App\Domain\Hotel::localTime($s->issued_at, 'd M') }}</span> <button class="btn-small btn-ghost">Sign out</button></form>
    @endforeach</td>
    <td>@if($d->active)
        <form method="post" action="/admin/devices/{{ $d->id }}/code" class="inline-form">@csrf<button class="btn-small btn-secondary">New pairing code</button></form>
        <form method="post" action="/admin/devices/revoke" class="inline-form">@csrf<input type="hidden" name="device_id" value="{{ $d->id }}"><button class="btn-small btn-danger">Revoke</button></form>
    @endif</td>
</tr>
@empty
<tr><td colspan="6" class="muted">No devices yet.</td></tr>
@endforelse
</tbody></table></div>
@endsection
