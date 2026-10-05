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
                <table>
                    <caption>Open visits</caption>
                    <thead><tr><th scope="col">Table</th><th scope="col">Opened at</th><th scope="col">Action</th></tr></thead>
                    <tbody>
                        @foreach ($activeVisits as $visit)
                            <tr>
                                <th scope="row">{{ $visit->table_id }}</th>
                                <td>{{ $visit->opened_at }}</td>
                                <td>
                                    <form method="post" action="/staff/visits/close">
                                        @csrf
                                        <input type="hidden" name="visit_id" value="{{ $visit->id }}">
                                        <button type="submit">Close visit</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
