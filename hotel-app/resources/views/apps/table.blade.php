@extends('layouts.device', ['title' => $hotel.' — Order'])
@section('bodyClass', 'app-body')
@section('content')
<div id="app" data-app="table" aria-live="polite"><main class="app-main"><p class="muted">Loading the menu…</p></main></div>
@endsection
@push('scripts')<script type="module" src="/assets/js/apps/table.js"></script>@endpush
