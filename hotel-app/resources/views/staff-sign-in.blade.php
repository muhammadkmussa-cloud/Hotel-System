<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <title>{{ config('app.name') }} — Staff sign in</title>
</head>
<body>
    <main id="main">
        <h1>Staff sign in</h1>
        @if ($errors->any())
            <div role="alert" class="state" tabindex="-1" data-error-focus>
                <h2>Sign in failed</h2>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form method="post" action="/staff/sign-in" data-single-submit>
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" autocomplete="username" required value="{{ old('email') }}" @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif>
                @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button type="submit" data-pending-label="Signing in…">Sign in</button>
            <p data-submit-status class="visually-hidden" role="status" aria-live="polite"></p>
        </form>
    </main>
    <script type="module">
        import { initSingleSubmit } from '/assets/js/lib/single-submit.js';
        initSingleSubmit(document.querySelector('form[data-single-submit]'));
        document.querySelector('[data-error-focus]')?.focus();
    </script>
</body>
</html>
