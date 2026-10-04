@extends('layout')
@section('modeNav')
<nav aria-label="Kitchen actions">
    <ul>
        <li><a href="/preview/kitchen" aria-current="page">Tickets</a></li>
        <li><span class="prototype-link">Stations (demo)</span></li>
    </ul>
</nav>
@endsection
@section('content')
    <h1>Kitchen display (prototype)</h1>
    <p>Prototype layout: dense, high-contrast tickets with table, guest, exclusions, and preparation state. No decorative photography.</p>
    <div class="ticket-columns">
        <article class="ticket" aria-labelledby="ticket-1">
            <h2 id="ticket-1">Table 4 · Guest 2</h2>
            <p class="ticket-meta">Submitted 12:04 · Hot station</p>
            <p class="ticket-state">Preparing</p>
            <p class="ticket-exclusion">No chilli — allergy note not implied</p>
        </article>
        <article class="ticket" aria-labelledby="ticket-2">
            <h2 id="ticket-2">Table 7 · Guest 1</h2>
            <p class="ticket-meta">Submitted 12:06 · Cold station</p>
            <p class="ticket-state">Queued</p>
            <p class="ticket-exclusion">No onions</p>
        </article>
    </div>
@endsection
