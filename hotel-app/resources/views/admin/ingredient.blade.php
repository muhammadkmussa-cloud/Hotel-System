@extends('layouts.staff', ['title' => $ing->name])
@section('content')
<p class="muted small"><a href="/admin/ingredients">← Ingredients</a></p>
<h1>{{ $ing->name }} @unless($ing->active)<span class="pill pill-danger">archived</span>@endunless</h1>
<div class="grid-2">
<section class="card">
<form method="post" action="/admin/ingredients/{{ $ing->id }}">@csrf
    <input type="hidden" name="version" value="{{ $ing->version }}">
    <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ $ing->name }}" required maxlength="120"></div>
    <div class="field"><label for="description">Description for guests</label><textarea id="description" name="description" maxlength="500">{{ $ing->description }}</textarea></div>
    <div class="field"><label for="allergen_notes">Allergen / dietary information</label><textarea id="allergen_notes" name="allergen_notes" maxlength="500">{{ $ing->allergen_notes }}</textarea></div>
    <div class="field"><label for="preparation_notes">Kitchen preparation notes</label><textarea id="preparation_notes" name="preparation_notes" maxlength="500">{{ $ing->preparation_notes }}</textarea></div>
    <div class="field"><label for="media_id">Photo</label><select id="media_id" name="media_id"><option value="">Placeholder</option>@foreach($mediaChoices as $mc)<option value="{{ $mc->id }}" @selected($ing->media_id === $mc->id)>{{ $mc->label ?: ($mc->original_stem ?: substr($mc->id, 0, 8)) }} — {{ $mc->publication_state }}</option>@endforeach</select>
        <p class="hint"><a href="/admin/media?kind=ingredient">Upload ingredient photos</a></p></div>
    <img src="{{ $image['src'] }}" alt="{{ $image['alt'] }}" width="160" height="160" style="border-radius:8px;margin-bottom:.75rem">
    <button>Save</button>
</form>
@if (count($usedBy))<p class="hint">Changing this ingredient sends these meals back for kitchen approval: {{ collect($usedBy)->pluck('name')->implode(', ') }}</p>@endif
<form method="post" action="/admin/ingredients/{{ $ing->id }}/archive" style="margin-top:1rem">@csrf<input type="hidden" name="active" value="{{ $ing->active ? '0' : '1' }}"><button class="btn-ghost">{{ $ing->active ? 'Archive' : 'Restore' }}</button></form>
</section>
<section class="card"><h2>Made from</h2>
<p class="hint">For prepared ingredients (e.g. a sauce), choose what it contains. Guests see the full list.</p>
<form method="post" action="/admin/ingredients/{{ $ing->id }}/components">@csrf
    <div style="max-height:320px;overflow:auto;border:1px solid var(--color-border);border-radius:8px;padding:.5rem">
    @foreach ($all as $a)
        <label class="check"><input type="checkbox" name="component_ids[]" value="{{ $a->id }}" @checked(collect($components)->contains('id', $a->id))> {{ $a->name }}</label>
    @endforeach
    </div>
    <button style="margin-top:.5rem">Save components</button>
</form>
@if (count($flat))<p class="small">Full contents: {{ implode(', ', $flat) }}</p>@endif
<h3>History</h3>
@foreach ($versions as $v)<p class="small muted">v{{ $v->version }} · {{ \App\Domain\Hotel::localTime($v->created_at, 'd M Y H:i') }}</p>@endforeach
</section>
</div>
@endsection
