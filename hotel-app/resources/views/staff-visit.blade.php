<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <title>{{ config('app.name') }} — Table {{ $visit['tableLabel'] }}</title>
</head>
<body>
    <main id="main">
        <h1>Table {{ $visit['tableLabel'] }}</h1>
        <p>
            Visit {{ $visit['state'] }}, opened {{ $visit['openedAt'] ?? 'just now' }}.
            Waiter: {{ $visit['ownerWaiterName'] ?? 'unassigned' }}.
        </p>
        <p>Balances and kitchen status appear in later build steps; this screen shows guests and tablets only.</p>
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

        <section aria-labelledby="guests-heading">
            <h2 id="guests-heading">Guests</h2>
            @if (count($guests) === 0)
                <p>No guests yet. Add one before handing a tablet over.</p>
            @else
                <ul>
                    @foreach ($guests as $guest)
                        <li>
                            <p>
                                {{ $guest['label'] }}@if ($guest['name'] !== null) — {{ $guest['name'] }}@endif
                                <span class="badge">{{ $guest['state'] }}</span>
                            </p>

                            @if (count($guest['devices']) === 0)
                                <p>No tablet is bound to this guest.</p>
                            @else
                                <ul>
                                    @foreach ($guest['devices'] as $binding)
                                        <li>
                                            {{ $binding['deviceName'] }}
                                            <form method="post" action="/staff/guest-bindings/{{ $binding['id'] }}/revoke" data-single-submit>
                                                @csrf
                                                <input type="hidden" name="visit_id" value="{{ $visit['id'] }}">
                                                <button type="submit">Unbind tablet</button>
                                            </form>
                                            <form method="post" action="/staff/guest-bindings/{{ $binding['id'] }}/replace" data-single-submit>
                                                @csrf
                                                <input type="hidden" name="visit_id" value="{{ $visit['id'] }}">
                                                <button type="submit">Replace tablet</button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if (count($bindableSessions) === 0)
                                <p>No active tablet sessions are available to bind.</p>
                            @else
                                <form method="post" action="/staff/guest-bindings" data-single-submit>
                                    @csrf
                                    <input type="hidden" name="guest_id" value="{{ $guest['id'] }}">
                                    <div class="field">
                                        <label for="bind-{{ $guest['id'] }}">Bind a tablet to {{ $guest['label'] }}</label>
                                        <select id="bind-{{ $guest['id'] }}" name="device_session_id" required>
                                            @foreach ($bindableSessions as $session)
                                                <option value="{{ $session['sessionId'] }}">{{ $session['deviceName'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit">Bind tablet</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($visit['state'] === 'open')
                <form method="post" action="/staff/visits/{{ $visit['id'] }}/guests" data-single-submit>
                    @csrf
                    <div class="field">
                        <label for="guest-name">Add a guest (optional name)</label>
                        <input id="guest-name" name="name" type="text" maxlength="150" autocomplete="off">
                    </div>
                    <button type="submit">Add guest</button>
                </form>
            @endif
        </section>

        @if ($canTransfer && $visit['state'] === 'open')
            <section aria-labelledby="transfer-heading">
                <h2 id="transfer-heading">Transfer this visit</h2>
                <p>Manager action. Guests, tablets and orders keep their identity; only the table or waiter changes.</p>
                <form method="post" action="/staff/visits/{{ $visit['id'] }}/transfers" data-single-submit>
                    @csrf
                    <input type="hidden" name="expected_version" value="{{ $visit['version'] }}">
                    <div class="field">
                        <label for="transfer-table">Destination table</label>
                        <select id="transfer-table" name="table_id">
                            <option value="">Keep current table</option>
                            @foreach ($transferTables as $table)
                                @if ($table['tableId'] !== $visit['tableId'])
                                    <option value="{{ $table['tableId'] }}">{{ $table['label'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="transfer-waiter">Owning waiter</label>
                        <select id="transfer-waiter" name="waiter_id">
                            <option value="">Unassigned</option>
                            @foreach ($waiters as $waiter)
                                <option value="{{ $waiter['id'] }}" @selected($waiter['id'] === $visit['ownerWaiterId'])>{{ $waiter['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit">Transfer visit</button>
                </form>
            </section>
        @endif

        @if ($visit['state'] === 'open')
            <section aria-labelledby="close-heading">
                <h2 id="close-heading">Close this visit</h2>
                <p>Available once every balance is settled; closure guards arrive with P23.</p>
                <form method="post" action="/staff/visits/{{ $visit['id'] }}/close" data-single-submit>
                    @csrf
                    <input type="hidden" name="expected_version" value="{{ $visit['version'] }}">
                    <button type="submit">Close visit</button>
                </form>
            </section>
        @endif

        <p><a href="/staff/tables">Back to tables</a></p>
    </main>
    <script type="module">
        import { initSingleSubmit } from '/assets/js/lib/single-submit.js';
        document.querySelectorAll('form[data-single-submit]').forEach(initSingleSubmit);
        document.querySelector('[data-error-focus]')?.focus();
    </script>
</body>
</html>
