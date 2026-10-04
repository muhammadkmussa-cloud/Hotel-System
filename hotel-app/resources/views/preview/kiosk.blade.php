@extends('layout')
@section('modeNav')
<nav aria-label="Kiosk actions">
    <ul>
        <li><a href="/preview/kiosk" aria-current="page">Choose meal</a></li>
        <li><span class="prototype-link">Review (demo)</span></li>
        <li><span class="prototype-link">Pay (demo)</span></li>
    </ul>
</nav>
@endsection
@section('content')
    <h1>Kiosk ordering (portrait prototype)</h1>
    <p>Prototype layout for the walk-in kiosk: two-column food grid with a reachable review action and payment below the fold.</p>
    <ol class="kiosk-steps" aria-label="Kiosk progress">
        <li aria-current="step">Choose meals</li>
        <li>Review</li>
        <li>Pay</li>
    </ol>
    <div class="meal-grid kiosk-grid">
        <article class="meal-card"><div class="meal-image" role="img" aria-label="Demo meal photograph placeholder">Demo image</div><h2>Fish curry</h2><p class="meal-price">Ksh 1,100.00</p><button type="button">Add</button></article>
        <article class="meal-card"><div class="meal-image" role="img" aria-label="Demo meal photograph placeholder">Demo image</div><h2>Beef stew</h2><p class="meal-price">Ksh 900.00</p><button type="button">Add</button></article>
        <article class="meal-card"><div class="meal-image" role="img" aria-label="Demo meal photograph placeholder">Demo image</div><h2>Vegetable pilau</h2><p class="meal-price">Ksh 700.00</p><button type="button">Add</button></article>
        <article class="meal-card"><div class="meal-image" role="img" aria-label="Demo meal photograph placeholder">Demo image</div><h2>Chapati set</h2><p class="meal-price">Ksh 250.00</p><button type="button">Add</button></article>
    </div>
    <div class="kiosk-review"><button type="button">Review and pay (demo)</button></div>
@endsection
