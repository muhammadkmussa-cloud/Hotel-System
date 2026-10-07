@extends('layouts.staff', ['title' => 'Ingredients'])
@section('content')
<div class="page-head"><h1>Ingredients</h1>
    <form method="get" class="inline-form"><label for="q" class="visually-hidden">Search</label><input id="q" name="q" value="{{ $q }}" placeholder="Search"><button class="btn-secondary">Search</button>
    <a class="btn btn-ghost" href="/admin/ingredients{{ $archived ? '' : '?archived=1' }}">{{ $archived ? 'Current' : 'Archived' }}</a></form>
</div>
<p class="muted">Ingredients are reusable across meals. Each meal decides whether an ingredient is fixed, removable or a paid extra. Notes here are shown to guests as information, not an allergen guarantee.</p>
<div class="grid-2">
<div>
<div class="table-wrap card"><table class="data"><tbody>
@forelse ($result['items'] as $i)
<tr><td style="width:52px"><img src="{{ $i->thumb['src'] }}" alt="" width="40" height="40" style="border-radius:6px;object-fit:cover"></td>
<td><a href="/admin/ingredients/{{ $i->id }}"><b>{{ $i->name }}</b></a>@if($i->allergen_notes)<br><span class="small muted">{{ $i->allergen_notes }}</span>@endif</td></tr>
@empty <tr><td class="muted">None found.</td></tr>
@endforelse
</tbody></table></div>
@if ($result['pages'] > 1)
<p>Page {{ $result['page'] }} of {{ $result['pages'] }}
    @if($result['page'] > 1)<a href="?q={{ urlencode($q) }}&page={{ $result['page'] - 1 }}">Previous</a>@endif
    @if($result['page'] < $result['pages'])<a href="?q={{ urlencode($q) }}&page={{ $result['page'] + 1 }}">Next</a>@endif</p>
@endif
</div>
<section class="card"><h2>New ingredient</h2>
<form method="post" action="/admin/ingredients">@csrf
    <div class="field"><label for="name">Name</label><input id="name" name="name" required maxlength="120"></div>
    <div class="field"><label for="description">Description for guests</label><textarea id="description" name="description" maxlength="500"></textarea></div>
    <div class="field"><label for="allergen_notes">Allergen / dietary information</label><textarea id="allergen_notes" name="allergen_notes" maxlength="500" placeholder="e.g. Contains milk."></textarea></div>
    <div class="field"><label for="preparation_notes">Kitchen preparation notes (staff only)</label><textarea id="preparation_notes" name="preparation_notes" maxlength="500"></textarea></div>
    <button>Create</button>
</form>
</section>
</div>
@endsection
