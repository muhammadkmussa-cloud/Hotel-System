@extends('layouts.staff', ['title' => 'Pair device'])
@section('mainClass', 'narrow')
@section('content')
<section class="card auth-card">
    <h1>Pair this device</h1>
    @if ($device)
        <div class="notice notice-ok"><p>This device is paired as <b>{{ $device['name'] }}</b>. Entering a new code replaces that pairing.</p>
            <p><a class="btn-secondary" href="/{{ match ($device['mode']) { 'tablet' => 'table', 'kiosk' => 'kiosk', 'kitchen' => 'kitchen', 'collection' => 'collection', default => '' } }}">Open this device’s screen</a></p></div>
    @endif
    <p class="muted">A manager creates a pairing code in <b>Admin → Devices</b>. Enter it here. Codes work once and expire after 15 minutes.</p>
    <form method="post" action="/device/pair">@csrf
        <div class="field">
            <label for="code">Pairing code</label>
            <input id="code" name="code" type="text" maxlength="64" required autocomplete="off" autocapitalize="characters" spellcheck="false" autofocus placeholder="ABCD-EFGH" class="code-input">
        </div>
        <button class="btn-large btn-block" data-pending-label="Pairing…">Pair device</button>
    </form>
    <p class="small muted">No private data is shown until this device is paired.</p>
    <p class="small muted">Staff? <a href="/staff/sign-in">Sign in instead</a>.</p>
</section>
@endsection
