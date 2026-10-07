@extends('layouts.staff', ['title' => 'Staff sign in'])
@section('mainClass', 'narrow')
@section('content')
<section class="card auth-card">
    <h1>Staff sign in</h1>
    <p class="muted">Use the email and password your manager gave you.</p>
    <form method="post" action="/staff/sign-in" data-single-submit>@csrf
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="username" required autofocus value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="btn-large btn-block" type="submit" data-pending-label="Signing in…">Sign in</button>
        <p class="visually-hidden" role="status" aria-live="polite" data-submit-status></p>
    </form>
    @php
        try { $demoInstall = \App\Domain\Hotel::testMode() && \Illuminate\Support\Facades\DB::table('staff_users')->where('email', 'owner@demo.test')->exists(); } catch (\Throwable) { $demoInstall = false; }
    @endphp
    @if ($demoInstall)
        <div class="notice notice-info small">
            <p><b>Demo installation.</b> Accounts: <code>owner@demo.test</code>, <code>manager@</code>, <code>cashier@</code>, <code>waiter@</code>, <code>kitchen-lead@</code>, <code>menu-editor@</code>, <code>auditor@demo.test</code> — password <code>demo-password-2026</code>.</p>
        </div>
    @endif
    <p class="small muted">Setting up a tablet, kiosk or kitchen screen? <a href="/device/pair">Pair a device</a>.</p>
</section>
@endsection

@push('scripts')
<script type="module">
    import { initSingleSubmit } from '/assets/js/lib/single-submit.js';
    document.querySelectorAll('form[data-single-submit]').forEach(initSingleSubmit);
</script>
@endpush
