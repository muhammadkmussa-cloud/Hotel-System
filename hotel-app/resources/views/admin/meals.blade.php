@extends('layouts.staff', ['title' => 'Meals'])
@section('content')
<div class="page-head">
    <h1>Meals</h1>
    <div class="btn-row"><a class="btn btn-ghost" href="/admin/ingredients">Ingredients</a><a class="btn btn-ghost" href="/admin/media">Photos</a>
        <a class="btn btn-secondary" href="/admin/meals{{ $archived ? '' : '?archived=1' }}">{{ $archived ? 'Show current' : 'Show archived' }}</a></div>
</div>
<p class="muted">Editing a meal changes a draft. Customers only see a version after the kitchen approves the exact recipe and a manager publishes it.</p>
<div class="table-wrap card"><table class="data">
<thead><tr><th></th><th>Meal</th><th>Category</th><th class="num">Price</th><th>Status</th><th>Availability</th></tr></thead><tbody>
@forelse ($meals as $m)
<tr>
    <td style="width:56px"><img src="{{ $m->thumb['src'] }}" alt="" width="48" height="48" style="border-radius:6px;object-fit:cover"></td>
    <td><a href="/admin/meals/{{ $m->id }}"><b>{{ $m->name }}</b></a></td>
    <td>{{ $m->category_name ?? '—' }}</td>
    <td class="num">{{ \App\Domain\Money::format((int) $m->price_minor) }}</td>
    <td><span class="pill {{ $m->status === 'published' ? 'pill-ok' : ($m->status === 'published_with_changes' ? 'pill-warn' : '') }}">{{ str_replace('_', ' ', $m->status) }}</span></td>
    <td>@if(! $m->sellable)<span class="pill pill-danger">unavailable</span>@elseif($m->portions_remaining !== null){{ $m->portions_remaining }} left @else — @endif</td>
</tr>
@empty
<tr><td colspan="6" class="muted">No meals yet.</td></tr>
@endforelse
</tbody></table></div>

@unless ($archived)
<div class="grid-2">
<section class="card"><h2>New meal</h2>
<form method="post" action="/admin/meals">@csrf
    <div class="field"><label for="name">Name</label><input id="name" name="name" required maxlength="120"></div>
    <div class="field"><label for="description">Description</label><textarea id="description" name="description" maxlength="1000"></textarea></div>
    <div class="form-grid">
        <div class="field"><label for="price">Price (KSh, tax inclusive)</label><input id="price" name="price" inputmode="decimal" required></div>
        <div class="field"><label for="display_order">Order</label><input id="display_order" name="display_order" type="number" min="0" value="0"></div>
        <div class="field"><label for="category_id">Category</label><select id="category_id" name="category_id"><option value="">—</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
        <div class="field"><label for="station_id">Kitchen station</label><select id="station_id" name="station_id"><option value="">Default</option>@foreach($stations as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->kind }})</option>@endforeach</select></div>
    </div>
    <button>Create draft</button>
</form>
</section>
<section class="card"><h2>Categories</h2>
    @foreach ($categories as $c)
    <form method="post" action="/admin/categories" class="inline-form" style="display:flex;margin-bottom:.4rem">@csrf
        <input type="hidden" name="id" value="{{ $c->id }}">
        <label class="visually-hidden" for="c-{{ $c->id }}">Name</label><input id="c-{{ $c->id }}" name="name" value="{{ $c->name }}" maxlength="80">
        <input name="display_order" type="number" min="0" value="{{ $c->display_order }}" style="width:5rem" aria-label="Order">
        <select name="active" aria-label="Shown"><option value="1" @selected($c->active)>shown</option><option value="0" @selected(! $c->active)>hidden</option></select>
        <button class="btn-small btn-secondary">Save</button>
    </form>
    @endforeach
    <form method="post" action="/admin/categories" class="inline-form" style="display:flex">@csrf
        <label class="visually-hidden" for="newcat">New category</label><input id="newcat" name="name" placeholder="New category" maxlength="80" required>
        <input name="display_order" type="number" min="0" value="{{ count($categories) }}" style="width:5rem" aria-label="Order">
        <button class="btn-small">Add</button>
    </form>
</section>
</div>
@endunless
@endsection
