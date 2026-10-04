<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <script type="module" src="/assets/js/main.js"></script>
    <title>{{ config('app.name') }}</title>
</head>
<body>
    <main>
        <h1>{{ config('app.name') }}</h1>
        <p>Service is being prepared.</p>
        @if (session('status'))
            <p role="status">{{ session('status') }}</p>
        @endif
        @if (session('staff_user_id'))
            <form method="post" action="/staff/sign-out">
                @csrf
                <button type="submit">Sign out</button>
            </form>
        @endif
        <p>Ordering is not available yet.</p>
        <p>Please ask a member of staff for assistance.</p>
        <button type="button" data-reload-page hidden>Check again</button>
    </main>
</body>
</html>
