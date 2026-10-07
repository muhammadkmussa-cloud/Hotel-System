@php
    $request = request();
    $staffId = \App\Security\Staff::id($request);
    $nav = $staffId ? \App\Http\StaffNav::items($request) : [];
    $hotelName = \App\Domain\Hotel::name();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <title>{{ $title ?? 'Staff' }} — {{ $hotelName }}</title>
</head>
<body class="staff-shell" @isset($live) data-live="{{ $live }}" @endisset>
<a class="skip-link" href="#main">Skip to main content</a>
<div class="offline" data-offline hidden role="status">Connection lost — retrying…</div>
<header class="staff-header">
    <div class="staff-brand">
        <a href="/staff">{{ $hotelName }}</a>
        @if (\App\Domain\Hotel::testMode())<span class="pill pill-warn" title="Test mode: receipts are marked TEST">Test mode</span>@endif
    </div>
    @if ($staffId)
        <nav aria-label="Staff" class="staff-nav">
            <ul>
                @foreach ($nav as $item)
                    <li><a href="{{ $item['href'] }}" @if($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
        <div class="staff-user">
            <span>{{ \App\Security\Staff::name($staffId) }}</span>
            <form method="post" action="/staff/sign-out">@csrf<button class="btn-link" type="submit">Sign out</button></form>
        </div>
    @endif
</header>
<main id="main" class="staff-main @yield('mainClass')">
    @if (session('status'))
        <div class="notice notice-ok" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="notice notice-error" role="alert" tabindex="-1" data-error-focus>
            <strong>That didn't work.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>
<script type="module" src="/assets/js/apps/staff.js"></script>
@stack('scripts')
</body>
</html>
