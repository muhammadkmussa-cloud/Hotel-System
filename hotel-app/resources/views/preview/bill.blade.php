@extends('layout')
@section('modeNav')
<nav aria-label="Bill actions">
    <ul>
        <li><a href="/preview/bill" aria-current="page">Bill</a></li>
        <li><span class="prototype-link">Split (demo)</span></li>
    </ul>
</nav>
@endsection
@section('content')
    <h1>Guest bill (prototype)</h1>
    <p>Charges, amount received, and amount due are shown separately with tabular figures.</p>
    <table class="bill-table">
        <caption>Table 4 · Guest 2 (demo)</caption>
        <tbody>
            <tr><th scope="row">Grilled tilapia</th><td>Ksh 1,200.00</td></tr>
            <tr><th scope="row">Shared chapati (half)</th><td>Ksh 125.00</td></tr>
            <tr><th scope="row">Total charges</th><td>Ksh 1,325.00</td></tr>
            <tr><th scope="row">Amount received</th><td>Ksh 1,000.00</td></tr>
            <tr><th scope="row">Amount due</th><td>Ksh 325.00</td></tr>
        </tbody>
    </table>
@endsection
