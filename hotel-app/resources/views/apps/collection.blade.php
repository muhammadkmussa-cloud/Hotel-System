@extends('layouts.device', ['title' => 'Collection — '.$hotel])
@section('bodyClass', 'collection-body')
@section('content')
<header class="collection-head"><b>{{ $hotel }}</b><span data-clock data-tz="{{ \App\Domain\Hotel::timezone() }}"></span></header>
<main id="app" class="collection-grid" data-app="collection">
    <section aria-labelledby="prep-h"><h2 id="prep-h">Preparing</h2><ul class="collection-numbers" data-preparing></ul></section>
    <section aria-labelledby="ready-h"><h2 id="ready-h">Ready to collect</h2><ul class="collection-numbers" data-ready aria-live="polite"></ul></section>
</main>
@endsection
@push('scripts')<script type="module" src="/assets/js/apps/collection.js"></script>@endpush
