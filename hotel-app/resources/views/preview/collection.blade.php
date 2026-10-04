@extends('layout')
@section('modeNav')
<nav aria-label="Collection actions">
    <ul>
        <li><a href="/preview/collection" aria-current="page">Collection board</a></li>
    </ul>
</nav>
@endsection
@section('content')
    <h1>Collection display (prototype)</h1>
    <p>Public-ready numbers only; no guest, payment, or private data.</p>
    <ol class="collection-list">
        <li><span class="collection-number">A-014</span> Ready</li>
        <li><span class="collection-number">A-015</span> Preparing</li>
    </ol>
@endsection
