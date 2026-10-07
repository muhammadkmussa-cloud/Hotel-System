@extends('layouts.staff', ['title' => 'Availability', 'live' => 'staff'])
@section('content')
<div class="page-head"><h1>Availability</h1></div>
<p class="muted">Mark dishes unavailable or set a portion count. Orders reserve portions when submitted; at zero the dish shows as sold out.</p>
<div class="table-wrap card"><table class="data">
<thead><tr><th>Meal</th><th>Now</th><th>Change</th></tr></thead><tbody>
@forelse ($meals as $m)
<tr>
    <td><b>{{ $m->name }}</b><br><span class="small muted">{{ $m->category_name }}</span></td>
    <td>@if(! $m->sellable)<span class="pill pill-danger">Unavailable</span>@elseif($m->portions_remaining === 0)<span class="pill pill-danger">Sold out</span>@elseif($m->portions_remaining !== null)<span class="pill pill-warn">{{ $m->portions_remaining }} left</span>@else<span class="pill pill-ok">Available</span>@endif
        @if($m->availability_reason)<br><span class="small muted">{{ $m->availability_reason }}</span>@endif</td>
    <td><form method="post" action="/admin/meals/{{ $m->id }}/availability" class="inline-form" style="display:flex">@csrf
        <input type="hidden" name="availability_version" value="{{ $m->availability_version }}">
        <select name="sellable" aria-label="Sellable for {{ $m->name }}"><option value="1" @selected($m->sellable)>Available</option><option value="0" @selected(! $m->sellable)>Unavailable</option></select>
        <input name="portions" value="{{ $m->portions_remaining }}" placeholder="No count" inputmode="numeric" style="width:7rem" aria-label="Portions for {{ $m->name }}">
        <input name="reason" value="{{ $m->availability_reason }}" placeholder="Reason" maxlength="200" aria-label="Reason">
        <button class="btn-small">Save</button></form></td>
</tr>
@empty
<tr><td colspan="3" class="muted">No published meals.</td></tr>
@endforelse
</tbody></table></div>
@endsection
