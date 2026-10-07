@extends('layouts.device', ['title' => $hotel.' — Self order'])
@section('bodyClass', 'app-body kiosk')
@section('content')
<div id="app" data-app="kiosk" aria-live="polite"><main class="kiosk-hero"><p class="muted">Loading…</p></main></div>
@endsection
@push('scripts')<script type="module" src="/assets/js/apps/kiosk.js"></script>@endpush
