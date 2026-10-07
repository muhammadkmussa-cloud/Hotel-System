@extends('layouts.staff', ['title' => $meal->name])
@section('content')
<p class="muted small"><a href="/admin/meals">← Meals</a></p>
<div class="page-head">
    <div><h1>{{ $meal->name }}</h1>
    <p><span class="pill {{ $meal->status === 'published' ? 'pill-ok' : 'pill-warn' }}">{{ str_replace('_', ' ', $meal->status) }}</span>
        @if($published) Live: version {{ $meal->published_version }} @else Not on the menu @endif</p></div>
</div>
@if ($meal->status === 'published_with_changes')
<div class="notice notice-warn">The draft differs from what customers see. The kitchen must approve the new recipe, then publish to replace version {{ $meal->published_version }}.</div>
@endif

<div class="grid-2">
<section class="card"><h2>Details</h2>
<form method="post" action="/admin/meals/{{ $meal->id }}">@csrf
    <input type="hidden" name="version" value="{{ $meal->version }}">
    <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ $meal->name }}" required maxlength="120"></div>
    <div class="field"><label for="description">Description</label><textarea id="description" name="description" maxlength="1000">{{ $meal->description }}</textarea></div>
    <div class="form-grid">
        <div class="field"><label for="price">Price (KSh, tax inclusive)</label><input id="price" name="price" inputmode="decimal" value="{{ number_format($meal->price_minor / 100, 2, '.', '') }}" required></div>
        <div class="field"><label for="display_order">Order in category</label><input id="display_order" name="display_order" type="number" min="0" value="{{ $meal->display_order }}"></div>
        <div class="field"><label for="category_id">Category</label><select id="category_id" name="category_id"><option value="">—</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected($meal->category_id === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="field"><label for="station_id">Kitchen station</label><select id="station_id" name="station_id"><option value="">Default</option>@foreach($stations as $s)<option value="{{ $s->id }}" @selected($meal->station_id === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    </div>
    <div class="field"><label for="media_id">Photo</label>
        <select id="media_id" name="media_id"><option value="">No photo (placeholder)</option>
        @foreach($mediaChoices as $mc)<option value="{{ $mc->id }}" @selected($meal->media_id === $mc->id)>{{ $mc->label ?: ($mc->original_stem ?: substr($mc->id, 0, 8)) }} — {{ $mc->publication_state }}</option>@endforeach
        </select>
        <p class="hint"><a href="/admin/media?kind=meal">Upload or manage photos</a>. The photo must be published (or a labelled demo) before the meal can be published.</p>
    </div>
    <img src="{{ $image['src'] }}" alt="{{ $image['alt'] }}" style="max-width:100%;border-radius:8px;margin-bottom:.75rem" width="{{ $image['width'] }}" height="{{ $image['height'] }}">
    @if ($canEdit)<button>Save draft</button>@endif
</form>
</section>

<section class="card"><h2>Ingredients and choices</h2>
<p class="hint">Fixed: always in the dish. Removable: guest may ask to leave it out. Extra: guest may add it for the price shown.</p>
<form method="post" action="/admin/meals/{{ $meal->id }}/ingredients">@csrf
    <input type="hidden" name="version" value="{{ $meal->version }}">
    @foreach ($rules as $i => $r)
        <div class="form-grid" style="align-items:end;border-bottom:1px solid var(--color-border);padding:.4rem 0">
            <div><b>{{ $r->name }}</b> @unless($r->active)<span class="pill pill-danger">archived</span>@endunless
                @if($r->allergen_notes)<br><span class="small muted">{{ $r->allergen_notes }}</span>@endif</div>
            <input type="hidden" name="rules[{{ $i }}][ingredient_id]" value="{{ $r->ingredient_id }}">
            <div class="field" style="margin:0"><label for="rule-{{ $i }}">Rule</label>
                <select id="rule-{{ $i }}" name="rules[{{ $i }}][rule]">
                    @foreach (['fixed' => 'Fixed', 'removable' => 'Removable', 'extra' => 'Extra (paid)', 'none' => 'Remove from meal'] as $k => $label)<option value="{{ $k }}" @selected($r->rule === $k)>{{ $label }}</option>@endforeach
                </select></div>
            <div class="field" style="margin:0"><label for="xp-{{ $i }}">Extra price</label><input id="xp-{{ $i }}" name="rules[{{ $i }}][extra_price]" inputmode="decimal" value="{{ number_format($r->extra_price_minor / 100, 2, '.', '') }}"></div>
        </div>
    @endforeach
    <div class="field" style="margin-top:.75rem"><label for="add">Add ingredient</label>
        <select id="add" name="add_ingredient_id"><option value="">—</option>@foreach($allIngredients as $ing)@unless(collect($rules)->contains('ingredient_id', $ing->id))<option value="{{ $ing->id }}">{{ $ing->name }}</option>@endunless @endforeach</select>
        <p class="hint">Missing one? <a href="/admin/ingredients">Create it in Ingredients</a>.</p></div>
    @if ($canEdit)<button>Save ingredients</button>@endif
</form>
</section>
</div>

<div class="grid-2">
<section class="card"><h2>Kitchen review</h2>
    @if ($meal->recipe_approved_digest === $meal->draft_digest && $meal->draft_digest)
        <p class="notice notice-ok">The kitchen approved this exact recipe @if($meal->recipe_approved_at) on {{ \App\Domain\Hotel::localTime($meal->recipe_approved_at, 'd M Y H:i') }}@endif.</p>
    @else
        <p>Please check what the customer will see:</p>
        <ul>
            @foreach ($facts['ingredients'] as $f)
                <li><b>{{ $f['name'] }}</b> ({{ $f['rule'] }}{{ $f['rule'] === 'extra' ? ' +'.\App\Domain\Money::format($f['extra_price_minor']) : '' }})
                    @if(count($f['components'])) — contains {{ implode(', ', $f['components']) }}@endif</li>
            @endforeach
        </ul>
        @if ($canReview)
        <form method="post" action="/admin/meals/{{ $meal->id }}/approve">@csrf
            <input type="hidden" name="digest" value="{{ $meal->draft_digest }}">
            <div class="field"><label for="anote">Note</label><input id="anote" name="note" maxlength="300"></div>
            <button>Approve recipe as listed</button>
        </form>
        @else <p class="muted">A kitchen lead or manager must approve.</p>
        @endif
    @endif
</section>
<section class="card"><h2>Publishing</h2>
    @if ($canPublish)
        <div class="btn-row">
        @if (! $meal->archived)
            <form method="post" action="/admin/meals/{{ $meal->id }}/publish">@csrf<button @disabled($meal->status === 'published')>Publish to menu</button></form>
            @if ($published)<form method="post" action="/admin/meals/{{ $meal->id }}/unpublish">@csrf<button class="btn-secondary">Take off menu</button></form>@endif
        @endif
        <form method="post" action="/admin/meals/{{ $meal->id }}/archive">@csrf<input type="hidden" name="archived" value="{{ $meal->archived ? '0' : '1' }}"><button class="btn-ghost">{{ $meal->archived ? 'Restore' : 'Archive' }}</button></form>
        </div>
    @else <p class="muted">A manager publishes approved meals.</p>
    @endif
    @if ($canAvailability && $published)
        <h3>Availability now</h3>
        <form method="post" action="/admin/meals/{{ $meal->id }}/availability" class="inline-form" style="display:flex">@csrf
            <input type="hidden" name="availability_version" value="{{ $meal->availability_version }}">
            <select name="sellable" aria-label="Sellable"><option value="1" @selected($meal->sellable)>Available</option><option value="0" @selected(! $meal->sellable)>Unavailable</option></select>
            <input name="portions" value="{{ $meal->portions_remaining }}" placeholder="Portions (blank = no count)" inputmode="numeric" aria-label="Portions">
            <input name="reason" value="{{ $meal->availability_reason }}" placeholder="Reason (optional)" maxlength="200" aria-label="Reason">
            <button class="btn-small">Update</button>
        </form>
    @endif
    <h3>Versions</h3>
    @forelse ($versions as $v)<p class="small">v{{ $v->version }} · {{ \App\Domain\Money::format((int) (json_decode($v->snapshot, true)['price_minor'] ?? 0)) }} · {{ \App\Domain\Hotel::localTime($v->published_at, 'd M Y H:i') }}</p>@empty<p class="muted small">Never published.</p>@endforelse
</section>
</div>
@endsection
