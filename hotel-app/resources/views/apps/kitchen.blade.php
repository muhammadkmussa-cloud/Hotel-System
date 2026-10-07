@extends('layouts.device', ['title' => 'Kitchen — '.$hotel])
@section('bodyClass', 'kitchen-body')
@section('content')
<header class="kitchen-top">
    <h1>Kitchen</h1>
    <label class="small">Station <select data-station><option value="">All stations</option></select></label>
    <span class="small" data-clock data-tz="{{ \App\Domain\Hotel::timezone() }}" aria-hidden="true"></span>
    <label class="small"><input type="checkbox" data-sound> Sound for new tickets</label>
    @if (! $device)<a href="/staff">Staff home</a>@endif
</header>
<main id="app" data-app="kitchen"><div class="kitchen-lanes" data-lanes><p>Loading tickets…</p></div></main>
<div class="visually-hidden" role="status" aria-live="assertive" data-announce></div>
@endsection
@push('scripts')<script type="module" src="/assets/js/apps/kitchen.js"></script>@endpush
