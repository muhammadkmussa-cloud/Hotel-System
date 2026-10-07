<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <title>{{ $title ?? $hotel }}</title>
</head>
<body class="@yield('bodyClass')">
<a class="skip-link" href="#app">Skip to content</a>
<div class="offline" data-offline hidden role="status">Connection lost — trying again…</div>
@if (! empty($testMode))<div class="notice notice-warn center" style="margin:0;border-radius:0">TEST MODE — orders and payments are not real.</div>@endif
@yield('content')
<noscript><div class="notice notice-error">This screen needs JavaScript enabled.</div></noscript>
@stack('scripts')
</body>
</html>
