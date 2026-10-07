@extends('layouts.device', ['title' => $hotel])
@section('bodyClass', 'app-body')
@section('content')
<main id="app" class="kiosk-hero" data-waiting>
    <div>
        <p class="muted">{{ $hotel }}</p>
        <h1>Welcome</h1>
        <p style="font-size:1.3rem">Your waiter will set up this tablet for you in a moment.</p>
        <p class="muted small">{{ $device['name'] }}</p>
    </div>
</main>
@endsection
@push('scripts')
<script type="module">
    // Poll until staff bind this tablet to a guest, then open the menu.
    async function check() {
        try {
            const r = await fetch('/api/v1/table/context', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (r.ok) { location.href = '/table'; return; }
            if (r.status === 401) {
                const body = await r.json().catch(() => ({}));
                if (body?.error?.code === 'DEVICE_REQUIRED') { location.href = '/device/pair'; return; }
            }
        } catch (e) { /* offline — keep waiting */ }
        setTimeout(check, 4000);
    }
    setTimeout(check, 2000);
</script>
@endpush
