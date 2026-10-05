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
        <p>Table status for this service. Balances and kitchen status appear in later build steps.</p>
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
                <p>No tables are configured yet. Add them in hotel settings.</p>
            @elseif (count($availableTables) === 0)
                <p>Every table already has an active visit.</p>
            @else
                <form method="post" action="/staff/visits/open" data-single-submit>
                    @csrf
                    <div class="field">
                        <label for="table_id">Table</label>
                        <select id="table_id" name="table_id" required>
                            @foreach ($availableTables as $table)
                                <option value="{{ $table['tableId'] }}">{{ $table['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit">Open visit</button>
                </form>
            @endif
        </section>

        <section aria-labelledby="table-status-heading">
            <h2 id="table-status-heading">Table status</h2>
            @if (count($tables) === 0)
                <p>No tables are configured yet.</p>
            @else
                <table>
                    <caption>{{ $occupiedCount }} of {{ count($tables) }} tables in use</caption>
                    <thead>
                        <tr>
                            <th scope="col">Table</th>
                            <th scope="col">Status</th>
                            <th scope="col">Guests</th>
                            <th scope="col">Opened</th>
                            <th scope="col">Balance</th>
                            <th scope="col">Kitchen</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tables as $table)
                            <tr>
                                <th scope="row">{{ $table['label'] }}</th>
                                <td>{{ $table['visitId'] === null ? 'Available' : 'In use' }}</td>
                                <td>{{ $table['guestCount'] }}</td>
                                <td>{{ $table['openedAt'] ?? '—' }}</td>
                                <td>
                                    <span class="badge">Placeholder</span>
                                    Not available yet (billing, P18)
                                </td>
                                <td>
                                    <span class="badge">Placeholder</span>
                                    Not available yet (kitchen, P16)
                                </td>
                                <td>
                                    @if ($table['visitId'] === null)
                                        <form method="post" action="/staff/visits/open" data-single-submit>
                                            @csrf
                                            <input type="hidden" name="table_id" value="{{ $table['tableId'] }}">
                                            <button type="submit">Open visit</button>
                                        </form>
                                    @else
                                        <a href="/staff/visits/{{ $table['visitId'] }}">Open visit details</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <p><a href="/staff/tables">Refresh</a></p>
    </main>
    <script type="module">
        import { initSingleSubmit } from '/assets/js/lib/single-submit.js';
        document.querySelectorAll('form[data-single-submit]').forEach(initSingleSubmit);
        document.querySelector('[data-error-focus]')?.focus();
    </script>
</body>
</html>
