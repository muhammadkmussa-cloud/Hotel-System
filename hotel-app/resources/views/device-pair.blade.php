<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <title>{{ config('app.name') }} — Pair device</title>
</head>
<body>
    <main id="main">
        <h1>Pair this device</h1>
        <p>Enter the pairing code provided by staff. Unpaired devices cannot see any private data.</p>
        @if (session('status'))
            <p role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="state" tabindex="-1" data-error-focus>
                <h2>Pairing failed</h2>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form method="post" action="/device/pair" data-single-submit>
            @csrf
            <div class="field">
                <label for="code">Pairing code</label>
                <input id="code" name="code" type="text" maxlength="64" required autocomplete="off">
            </div>
            <button type="submit" data-pending-label="Pairing…">Pair device</button>
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
