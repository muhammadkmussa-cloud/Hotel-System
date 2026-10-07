@extends('layouts.staff', ['title' => 'Audit log'])
@section('content')
<div class="page-head"><h1>Audit log</h1>
<form method="get" class="inline-form" style="display:flex">
    <label for="event" class="visually-hidden">Event</label>
    <input id="event" name="event" value="{{ $event }}" list="events" placeholder="Event (prefix)">
    <datalist id="events">@foreach($events as $e)<option value="{{ $e }}">@endforeach</datalist>
    <label for="actor" class="visually-hidden">Staff</label>
    <select id="actor" name="actor"><option value="">Anyone</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected($actor === $s->id)>{{ $s->name }}</option>@endforeach</select>
    <button class="btn-secondary">Filter</button>
</form></div>
<div class="table-wrap card"><table class="data"><thead><tr><th>When</th><th>Who</th><th>Event</th><th>Details</th></tr></thead><tbody>
@forelse ($rows as $r)
<tr><td style="white-space:nowrap">{{ \App\Domain\Hotel::localTime($r->created_at, 'd M Y H:i:s') }}</td><td>{{ $r->actor_name ?? 'System' }}</td><td><code>{{ $r->event }}</code></td>
<td class="small">@foreach ((json_decode((string) $r->context, true) ?: []) as $k => $v)<span>{{ $k }}={{ is_scalar($v) ? var_export($v, true) : json_encode($v) }}</span> @endforeach</td></tr>
@empty<tr><td colspan="4" class="muted">No events.</td></tr>@endforelse
</tbody></table></div>
<p>@if($page > 1)<a href="?event={{ urlencode($event) }}&actor={{ $actor }}&page={{ $page - 1 }}">Newer</a>@endif
@if($more) <a href="?event={{ urlencode($event) }}&actor={{ $actor }}&page={{ $page + 1 }}">Older</a>@endif</p>
@endsection
