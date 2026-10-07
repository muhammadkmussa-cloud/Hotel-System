@extends('layouts.staff', ['title' => 'Photos'])
@section('content')
<div class="page-head"><h1>Photos</h1>
<div class="btn-row"><a class="btn {{ $kind === 'meal' ? '' : 'btn-secondary' }}" href="/admin/media?kind=meal">Meals</a><a class="btn {{ $kind === 'ingredient' ? '' : 'btn-secondary' }}" href="/admin/media?kind=ingredient">Ingredients</a></div></div>
<section class="card"><h2>Upload</h2>
<form method="post" action="/admin/media" enctype="multipart/form-data" class="inline-form" data-media-uploader style="display:flex;flex-wrap:wrap">@csrf
    <input type="hidden" name="kind" value="{{ $kind }}">
    <label for="file" class="visually-hidden">Photo file</label><input id="file" type="file" name="file" accept="image/jpeg,image/png,image/webp" required>
    <label class="check"><input type="checkbox" name="demo" value="1"> Demo/sample photo (labelled “sample image” to guests)</label>
    <button type="submit" data-pending-label="Uploading…">Upload</button>
</form>
<div class="upload-preview" hidden data-upload-preview>
    <img alt="Selected photo preview" data-upload-preview-img width="160" height="160" style="max-width:160px;height:auto;border-radius:6px">
    <p class="small muted" data-upload-name></p>
</div>
<progress max="100" value="0" hidden data-upload-progress aria-label="Upload progress"></progress>
<p class="visually-hidden" role="status" aria-live="polite" data-upload-status></p>
<p class="field-error" role="alert" hidden data-upload-error></p>
<p class="hint">JPEG, PNG or WebP. Location and camera metadata are removed; resized copies are generated automatically. A photo is saved as a draft until you add alt text and publish it.</p>
</section>
@push('scripts')
<script type="module">
    import { initMediaUploader } from '/assets/js/apps/media-upload.js';
    document.querySelectorAll('form[data-media-uploader]').forEach(initMediaUploader);
</script>
@endpush
<div class="meal-grid">
@forelse ($items as $m)
    <a class="card" href="/admin/media/{{ $m->id }}" style="text-decoration:none;color:inherit;padding:.5rem">
        <img src="{{ $m->thumb['src'] }}" alt="" width="160" height="160" style="width:100%;height:auto;border-radius:6px">
        <div class="small">{{ $m->label ?: ($m->original_stem ?: 'Untitled') }}</div>
        <span class="pill {{ $m->publication_state === 'published' ? 'pill-ok' : '' }}">{{ $m->publication_state }}</span>
        @if (! $m->alt_text)<span class="pill pill-warn">needs alt text</span>@endif
    </a>
@empty <p class="muted">No photos yet.</p>
@endforelse
</div>
@endsection
