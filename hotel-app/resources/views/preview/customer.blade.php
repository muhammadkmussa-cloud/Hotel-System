@extends('layout')
@section('modeNav')
<nav aria-label="Customer actions">
    <ul>
        <li><a href="/preview/customer" aria-current="page">Menu</a></li>
        <li><span class="prototype-link">My orders (demo)</span></li>
        <li><span class="prototype-link">My bill (demo)</span></li>
        <li><span class="prototype-link">Call waiter (demo)</span></li>
    </ul>
</nav>
@endsection
@section('content')
    <h1>Table menu (tablet prototype)</h1>
    <p>Prototype layout for the bound-guest tablet: meal photography, visible price, ingredient customiser, and a sticky cart action.</p>
    <div class="meal-grid">
        <article class="meal-card">
            <div class="meal-image" role="img" aria-label="Demo meal photograph placeholder">Demo image</div>
            <h2>Grilled tilapia</h2>
            <p class="meal-price">Ksh 1,200.00</p>
            <button type="button">Customise</button>
        </article>
        <article class="meal-card">
            <div class="meal-image" role="img" aria-label="Demo meal photograph placeholder">Demo image</div>
            <h2>Nyama choma plate</h2>
            <p class="meal-price">Ksh 950.00</p>
            <button type="button">Customise</button>
        </article>
    </div>
    <aside class="cart-bar" aria-label="Cart">
        <p>3 items · Ksh 3,150.00 (demo)</p>
        <button type="button">Review order</button>
    </aside>
@endsection
