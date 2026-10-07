@extends('layouts.staff', ['title' => 'Dashboard', 'live' => 'staff'])
@section('content')
<div class="page-head">
    <div><h1>Hello, {{ $name }}</h1><p class="muted">{{ implode(', ', array_map(fn ($r) => str_replace('_', ' ', $r), $roles)) ?: 'No role assigned yet — ask an owner to grant one.' }} · Business day {{ \App\Domain\Hotel::businessDate() }}</p></div>
</div>
@foreach ($alerts as [$text, $href])
    <div class="notice notice-warn"><a href="{{ $href }}">{{ $text }}</a></div>
@endforeach
<div class="stats">
    @foreach ($cards as [$label, $value, $href])
        <a class="stat" href="{{ $href }}" style="text-decoration:none;color:inherit"><b>{{ $value }}</b><span>{{ $label }}</span></a>
    @endforeach
</div>
@if (count($cards) === 0)
    <div class="notice notice-info">Your account has no operational role yet. An owner or manager can assign roles in Staff.</div>
@endif
<div class="card">
    <h2>Screens for devices</h2>
    <p class="muted">Pair a tablet, kiosk, kitchen screen or collection display at <a href="/device/pair">/device/pair</a> using a code from <a href="/admin/devices">Devices</a>.</p>
    <ul>
        <li><a href="/kitchen">Kitchen board</a> — live tickets per station</li>
        <li><a href="/collection">Collection display</a> — kiosk numbers being prepared and ready</li>
    </ul>
</div>
@endsection
