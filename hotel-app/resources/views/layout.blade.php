<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <script type="module" src="/assets/js/main.js"></script>
    <title>{{ config('app.name') }} — {{ $title ?? 'Prototype' }}</title>
</head>
<body>
    <a class="skip-link" href="#main">Skip to main content</a>
    <header>
        <p class="demo-banner">Prototype screen — demo data only, not a live hotel system.</p>
        <nav aria-label="Prototype screens">
            <ul>
                <li><a href="/preview/customer">Customer</a></li>
                <li><a href="/preview/kiosk">Kiosk</a></li>
                <li><a href="/preview/staff">Staff</a></li>
                <li><a href="/preview/kitchen">Kitchen</a></li>
                <li><a href="/preview/collection">Collection</a></li>
                <li><a href="/preview/cashier">Cashier</a></li>
                <li><a href="/preview/bill">Bill</a></li>
            </ul>
        </nav>
        @yield('modeNav')
    </header>
    <main id="main">
        @yield('content')
    </main>
    <footer>
        <p>Hotel System prototype. Screens show labelled demo data only; no live ordering, payments, or private records.</p>
    </footer>
</body>
</html>
