@extends('layout')
@section('modeNav')
<nav aria-label="Staff actions">
    <ul>
        <li><a href="/preview/staff" aria-current="page">Tables</a></li>
        <li><span class="prototype-link">Cashier (demo)</span></li>
        <li><span class="prototype-link">Kitchen (demo)</span></li>
    </ul>
</nav>
@endsection
@section('content')
    <h1>Staff overview (prototype)</h1>
    <p>Dense operational view of open tables and preparation state.</p>
    <table>
        <caption>Open tables (demo)</caption>
        <thead><tr><th scope="col">Table</th><th scope="col">Guests</th><th scope="col">State</th></tr></thead>
        <tbody>
            <tr><th scope="row">Table 4</th><td>3</td><td><span class="badge">Ordering</span></td></tr>
            <tr><th scope="row">Table 7</th><td>2</td><td><span class="badge">Awaiting payment</span></td></tr>
        </tbody>
    </table>
@endsection
