@extends('layouts.staff', ['title' => 'Locked'])
@section('mainClass', 'narrow')
@section('content')
<section class="card auth-card">
    <h1>Session locked</h1>
    <p class="muted">This screen was idle. Re-enter your password to continue.</p>
    <form method="post" action="/staff/unlock">@csrf
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required autofocus @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            @error('password')<p id="password-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <button class="btn-large btn-block" data-pending-label="Unlocking…">Unlock</button>
    </form>
    <form method="post" action="/staff/sign-out" class="inline-form">@csrf<button class="btn-link">Sign out instead</button></form>
</section>
@endsection
