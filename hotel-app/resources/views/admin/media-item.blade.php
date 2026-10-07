@extends('layouts.staff', ['title' => 'Photo'])
@section('content')
<p class="muted small"><a href="/admin/media?kind={{ $m->kind }}">← Photos</a></p>
<h1>{{ $m->label ?: ($m->original_stem ?: 'Photo') }} <span class="pill">{{ $m->publication_state }}</span></h1>
<div class="grid-2">
<section class="card">
    <img src="{{ $card['src'] }}" alt="{{ $card['alt'] }}" width="{{ $card['width'] }}" height="{{ $card['height'] }}" style="width:100%;height:auto;border-radius:8px">
    <p class="small muted">{{ $m->master_width }}×{{ $m->master_height }} · {{ count($variants) }} generated sizes @if($card['placeholder'])· <b>processing failed or pending — cannot publish</b>@endif</p>
    <div class="btn-row">
        @foreach (['published' => 'Publish', 'demo' => 'Mark as demo', 'draft' => 'Back to draft', 'archived' => 'Archive'] as $state => $label)
            @if ($m->publication_state !== $state)
            <form method="post" action="/admin/media/{{ $m->id }}/state">@csrf<input type="hidden" name="state" value="{{ $state }}"><button class="{{ $state === 'published' ? '' : 'btn-secondary' }} btn-small">{{ $label }}</button></form>
            @endif
        @endforeach
    </div>
    @if (count($usedBy))<p class="small">Used by: {{ collect($usedBy)->pluck('name')->implode(', ') }}</p>@endif
</section>
<section class="card">
<form method="post" action="/admin/media/{{ $m->id }}">@csrf
    <input type="hidden" name="version" value="{{ $m->version }}">
    <div class="field"><label for="label">Internal label</label><input id="label" name="label" value="{{ $m->label }}" maxlength="200"></div>
    <div class="field"><label for="alt">Alt text (describe the photo for screen readers)</label><textarea id="alt" name="alt_text" maxlength="1000" required>{{ $m->alt_text }}</textarea></div>
    <div class="form-grid">
        <div class="field"><label for="ro">Rights owner</label><input id="ro" name="rights_owner" value="{{ $m->rights_owner }}" maxlength="150"></div>
        <div class="field"><label for="rg">Rights granted</label><input id="rg" type="date" name="rights_granted_at" value="{{ $m->rights_granted_at }}"></div>
    </div>
    <div class="field"><label for="rs">Rights summary</label><input id="rs" name="rights_summary" value="{{ $m->rights_summary }}" maxlength="200"></div>
    <div class="field"><label for="rr">Restrictions</label><input id="rr" name="rights_restriction" value="{{ $m->rights_restriction }}" maxlength="300"></div>
    <div class="form-grid">
        <div class="field"><label for="fx">Focus point across (%)</label><input id="fx" type="number" min="0" max="100" step="1" name="focal_x" value="{{ round($m->focal_x / 100) }}"></div>
        <div class="field"><label for="fy">Focus point down (%)</label><input id="fy" type="number" min="0" max="100" step="1" name="focal_y" value="{{ round($m->focal_y / 100) }}"></div>
    </div>
    <button>Save details</button>
</form>
<h3>Hotel approval</h3>
<p class="small muted" data-approval-summary>
    @if ($m->content_approver_name || $m->chef_approver_name)
        Content: {{ $m->content_approver_name ?: '—' }}@if($m->content_approved_at) ({{ $m->content_approved_at }})@endif ·
        Chef: {{ $m->chef_approver_name ?: '—' }}@if($m->chef_approved_at) ({{ $m->chef_approved_at }})@endif
    @else
        Not yet approved. Demo/sample assets stay labelled “sample image”.
    @endif
</p>
<form method="post" action="/admin/media/{{ $m->id }}/approve">@csrf
    <div class="form-grid">
        <div class="field"><label for="ca">Content approver</label><input id="ca" name="content_approver" value="{{ $m->content_approver_name }}" maxlength="150"></div>
        <div class="field"><label for="cha">Chef approver</label><input id="cha" name="chef_approver" value="{{ $m->chef_approver_name }}" maxlength="150"></div>
    </div>
    <button>Record approval</button>
</form>
<h3>Edit history</h3>
@forelse ($edits as $e)<p class="small muted">{{ \App\Domain\Hotel::localTime($e->created_at, 'd M H:i') }} · v{{ $e->from_version }}→v{{ $e->to_version }} · {{ implode(', ', array_keys(json_decode($e->changes, true) ?: [])) }}</p>@empty<p class="small muted">No edits.</p>@endforelse
</section>
</div>
@endsection
