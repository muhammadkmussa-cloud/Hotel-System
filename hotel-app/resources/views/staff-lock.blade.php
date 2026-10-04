<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <title>{{ config('app.name') }} — Locked</title>
</head>
<body>
    <main id="main">
        <h1>Session locked</h1>
        <p>This tablet has been idle. Re-enter your password to continue; customer mode does not grant staff access.</p>
        @if ($errors->any())
            <div role="alert" class="state" tabindex="-1" data-error-focus>
                <h2>Unlock failed</h2>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form method="post" action="/staff/unlock" data-single-submit>
            @csrf
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
                @error('password')<p id="password-error" class="field-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" data-pending-label="Unlocking…">Unlock</button>
            <p data-submit-status class="visually-hidden" role="status" aria-live="polite"></p>
        </form>
        <form method="post" action="/staff/sign-out">
            @csrf
            <button type="submit">Sign out instead</button>
        </form>
    </main>
    <script type="module">
        import { initSingleSubmit } from '/assets/js/lib/single-submit.js';
        initSingleSubmit(document.querySelector('form[data-single-submit]'));
        document.querySelector('[data-error-focus]')?.focus();
    </script>
</body>
</html>
