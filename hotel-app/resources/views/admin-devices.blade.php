<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <title>{{ config('app.name') }} — Devices</title>
</head>
<body>
    <main id="main">
        <h1>Devices</h1>
        <p>Enrolled devices and their status. Only owners and managers can revoke devices.</p>
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
        @if (count($devices) === 0)
            <p>No devices are enrolled yet.</p>
        @else
            <table>
                <caption>Enrolled devices</caption>
                <thead><tr><th scope="col">Name</th><th scope="col">Mode</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                <tbody>
                    @foreach ($devices as $device)
                        <tr>
                            <th scope="row">{{ $device['name'] }}</th>
                            <td>{{ $device['mode'] }}</td>
                            <td>{{ $device['active'] ? 'Active' : 'Inactive' }}</td>
                            <td>
                                @if ($device['active'])
                                    <form method="post" action="/admin/devices/revoke">
                                        @csrf
                                        <input type="hidden" name="device_id" value="{{ $device['id'] }}">
                                        <button type="submit">Revoke</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </main>
    <script type="module">
        import { initSingleSubmit } from '/assets/js/lib/single-submit.js';
        document.querySelectorAll('form[data-single-submit]').forEach(initSingleSubmit);
        document.querySelector('[data-error-focus]')?.focus();
    </script>
</body>
</html>
