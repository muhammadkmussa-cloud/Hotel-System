<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <title>{{ config('app.name') }} — Setup</title>
</head>
<body>
    <main id="main">
        <h1>First installation setup</h1>
        <p>Configure this hotel's identity. This screen is available only during initial setup.</p>

        @if ($errors->any())
            <div role="alert" class="state">
                <h2>Check these details</h2>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="/setup">
            @csrf
            <div class="field">
                <label for="name">Hotel trading name</label>
                <input id="name" name="name" type="text" maxlength="150" required value="{{ old('name') }}" aria-describedby="name-hint">
                <p id="name-hint">Shown on receipts and staff screens.</p>
            </div>
            <div class="field">
                <label for="timezone">Timezone</label>
                <select id="timezone" name="timezone" required>
                    @foreach ($timezones as $timezone)
                        <option value="{{ $timezone }}" @selected(old('timezone', 'Africa/Nairobi') === $timezone)>{{ $timezone }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="currency">Currency</label>
                <input id="currency" name="currency" type="text" value="{{ $currency }}" readonly aria-describedby="currency-hint">
                <p id="currency-hint">This release supports KES only.</p>
            </div>
            <div class="field">
                <input id="test_mode" name="test_mode" type="checkbox" value="1" aria-describedby="test-mode-hint" @checked(old('test_mode'))>
                <label for="test_mode">Test mode</label>
                <p id="test-mode-hint">Mark this installation as a non-live trial.</p>
            </div>
            <div class="field">
                <label for="installer_secret">Installer secret</label>
                <input id="installer_secret" name="installer_secret" type="password" required autocomplete="off" aria-describedby="installer-secret-hint">
                <p id="installer-secret-hint">The private secret set as INSTALLER_SECRET for this installation.</p>
            </div>
            <button type="submit">Save installation settings</button>
        </form>
    </main>
</body>
</html>
