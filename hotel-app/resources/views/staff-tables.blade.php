<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <title>{{ config('app.name') }} — Tables</title>
</head>
<body>
    <main id="main">
        <h1>Tables</h1>
        <p>Open a visit for a table and close it once balances are settled.</p>
        @if (session('status'))
            <p role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="state" tabindex="-1" data-error-focus>
                <h2>Check these details</h2>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section aria-labelledby="open-visit-heading">
            <h2 id="open-visit-heading">Open a visit</h2>
            @if (count($tables) === 0)
                <p>No tables are configured yet.</p>
            @else
                <form method="post" action="/staff/visits/open">
                    @csrf
                    <div class="field">
                        <label for="table_id">Table</label>
                        <select id="table_id" name="table_id" required>
                            @foreach ($tables as $table)
                                <option value="{{ $table->id }}">{{ $table->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit">Open visit</button>
                </form>
            @endif
        </section>

        <section aria-labelledby="active-visits-heading">
            <h2 id="active-visits-heading">Active visits</h2>
            @if (count($activeVisits) === 0)
                <p>No active visits.</p>
            @else
                @foreach ($activeVisits as $visit)
                    <article class="state" aria-labelledby="visit-{{ $visit['id'] }}-heading">
                        <h3 id="visit-{{ $visit['id'] }}-heading">Table {{ $visit['tableLabel'] }}</h3>
                        <p>Opened {{ $visit['openedAt'] ?? 'just now' }}.</p>

                        <h4>Guests</h4>
                        @if (count($visit['guests']) === 0)
                            <p>No guests yet. Add one before handing a tablet over.</p>
                        @else
                            <ul>
                                @foreach ($visit['guests'] as $guest)
                                    <li>
                                        {{ $guest['label'] }}@if ($guest['name'] !== null) — {{ $guest['name'] }}@endif
                                        <span class="badge">{{ $guest['state'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <form method="post" action="/staff/visits/{{ $visit['id'] }}/guests" data-single-submit>
                            @csrf
                            <div class="field">
                                <label for="guest-name-{{ $visit['id'] }}">Guest name (optional)</label>
                                <input id="guest-name-{{ $visit['id'] }}" name="name" type="text" maxlength="150" autocomplete="off">
                            </div>
                            <button type="submit">Add guest</button>
                        </form>

                        <form method="post" action="/staff/visits/{{ $visit['id'] }}/close" data-single-submit>
                            @csrf
                            <input type="hidden" name="expected_version" value="{{ $visit['version'] }}">
                            <button type="submit">Close visit</button>
                        </form>
                    </article>
                @endforeach
            @endif
        </section>
    </main>
    <script type="module">
        import { initSingleSubmit } from '/assets/js/lib/single-submit.js';
        document.querySelectorAll('form[data-single-submit]').forEach(initSingleSubmit);
        document.querySelector('[data-error-focus]')?.focus();
    </script>
</body>
</html>
