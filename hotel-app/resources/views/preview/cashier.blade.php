@extends('layout')
@section('modeNav')
<nav aria-label="Cashier actions">
    <ul>
        <li><a href="/preview/cashier" aria-current="page">Balances</a></li>
        <li><span class="prototype-link">Drawer (demo)</span></li>
        <li><span class="prototype-link">Reports (demo)</span></li>
    </ul>
</nav>
@endsection
@section('content')
    <h1>Cashier (prototype)</h1>
    <p>Guest balances, payment status, and method are separated so "payment received" and "close visit" are never the same control.</p>
    <table>
        <caption>Open balances (demo)</caption>
        <thead><tr><th scope="col">Guest</th><th scope="col">Balance</th><th scope="col">Payment status</th></tr></thead>
        <tbody>
            <tr><th scope="row">Table 4</th><td>Ksh 3,150.00</td><td><span class="badge">Unpaid</span></td></tr>
            <tr><th scope="row">Table 7</th><td>Ksh 1,400.00</td><td><span class="badge">M-PESA pending</span></td></tr>
        </tbody>
    </table>
    <section aria-labelledby="payment-heading">
        <h2 id="payment-heading">Record payment (demo)</h2>
        <div class="method-blocks">
            <button type="button">Cash received</button>
            <button type="button">Card terminal confirmed</button>
            <button type="button">M-PESA verified</button>
        </div>
    </section>
@endsection
