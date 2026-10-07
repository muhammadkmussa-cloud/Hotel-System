@extends('layouts.staff', ['title' => 'Bill'])
@section('content')
<div class="btn-row no-print"><button onclick="window.print()">Print</button></div>
<div class="receipt">
<b>{{ \App\Domain\Hotel::name() }}</b>
BILL — NOT A RECEIPT
{{ $guest->table_label }} · {{ $guest->label }}@if($guest->name) ({{ $guest->name }})@endif

{{ now(\App\Domain\Hotel::timezone())->format('d M Y H:i') }}
------------------------------------------
@foreach ($bill['lines'] as $l)
{{ $l['quantity'] }} x {{ $l['name'] }}@if($l['shared']) (share 1/{{ $l['shareCount'] }})@endif  {{ $l['amount'] }}@if($l['state'] === 'paid') PAID @endif

@endforeach
------------------------------------------
Paid:    {{ $bill['paid'] }}
DUE:     {{ $bill['balance'] }}
</div>
@endsection
