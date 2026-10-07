@php
    // The landing page must render even before the database is configured.
    try { $hotelName = \App\Domain\Hotel::name(); } catch (\Throwable) { $hotelName = config('app.name'); }
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script type="module" src="/assets/js/main.js"></script>
    <title>{{ $hotelName }}</title>
</head>
<body class="staff-shell">
<header class="staff-header"><div class="staff-brand"><a href="/">{{ $hotelName }}</a></div></header>
<main id="main" class="staff-main narrow">
    @if (session('status'))<div class="notice notice-ok" role="status">{{ session('status') }}</div>@endif
    <section class="card auth-card center">
        <h1>{{ $hotelName }}</h1>
        <p class="muted">Restaurant ordering and point of sale.</p>
        <div class="btn-stack">
            <a class="btn btn-large btn-block" href="/staff">Staff sign in</a>
            <a class="btn btn-secondary btn-large btn-block" href="/device/pair">Pair a tablet, kiosk or kitchen screen</a>
        </div>
        <p class="small muted">Guests: please ask a member of staff to set up your table tablet.</p>
    </section>
</main>
</body>
</html>
