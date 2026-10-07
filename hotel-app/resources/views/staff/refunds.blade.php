@extends('layouts.staff', ['title' => 'Refunds'])
@section('content')
<div class="page-head"><h1>Refunds</h1>
    <form method="get" class="inline-form"><label for="q" class="visually-hidden">Receipt or payment reference</label><input id="q" name="receipt" value="{{ $q }}" placeholder="Receipt no. or reference"><button class="btn-secondary">Find payment</button></form>
</div>
@if ($q !== '')
<section class="card"><h2>Payments for “{{ $q }}”</h2>
    @forelse ($payments as $p)
        <div class="card">
            <p><b>{{ strtoupper($p->method) }}</b> {{ \App\Domain\Money::format((int) $p->amount_minor) }} · receipt {{ $p->receipt_number }} {{ $p->reference }} · refunded {{ \App\Domain\Money::format((int) $p->refunded_minor) }}</p>
            @if ($p->state === 'applied')
            <form method="post" action="/staff/payments/{{ $p->id }}/refunds">@csrf
                <div class="form-grid">
                    <div class="field"><label for="a-{{ $p->id }}">Amount</label><input id="a-{{ $p->id }}" name="amount" inputmode="decimal" required></div>
                    <div class="field"><label for="r-{{ $p->id }}">Reason</label><input id="r-{{ $p->id }}" name="reason" maxlength="300" required></div>
                </div>
                <button>Request refund</button>
            </form>
            @endif
        </div>
    @empty <p class="muted">No payments found.</p>
    @endforelse
</section>
@endif
@if (count($unapplied))
<section class="card"><h2>Money received for closed orders</h2>
    <p class="muted">These M-PESA payments arrived after the order was cancelled or expired. Refund them via M-PESA reversal and record the reference.</p>
    @foreach ($unapplied as $u)<p>{{ \App\Domain\Money::format((int) $u->amount_minor) }} · {{ $u->reference }} · {{ \App\Domain\Hotel::localTime($u->created_at, 'd M H:i') }}</p>@endforeach
</section>
@endif
<section class="card"><h2>Refund requests</h2>
<div class="table-wrap"><table class="data"><thead><tr><th>When</th><th>Receipt</th><th>Method</th><th class="num">Amount</th><th>Reason</th><th>State</th><th></th></tr></thead><tbody>
@forelse ($refunds as $r)
    <tr>
        <td>{{ \App\Domain\Hotel::localTime($r->created_at, 'd M H:i') }}<br><span class="small muted">{{ $r->requested_by_name }}</span></td>
        <td>{{ $r->receipt_number }}</td><td>{{ $r->method }}</td><td class="num">{{ \App\Domain\Money::format((int) $r->amount_minor) }}</td>
        <td>{{ $r->reason }}</td><td><span class="pill">{{ $r->state }}</span> {{ $r->external_reference }}</td>
        <td>
            @if ($r->state === 'requested' && $canApprove)
                <form method="post" action="/staff/refunds/{{ $r->id }}/decide" class="inline-form">@csrf<button name="decision" value="approve" class="btn-small">Approve</button><button name="decision" value="reject" class="btn-small btn-secondary">Reject</button></form>
            @elseif ($r->state === 'approved' && $canComplete)
                <form method="post" action="/staff/refunds/{{ $r->id }}/complete" class="inline-form">@csrf
                    @if ($r->method !== 'cash')<input name="reference" placeholder="{{ $r->method === 'card' ? 'Terminal refund ref' : 'M-PESA reversal ref' }}" required maxlength="40">@endif
                    <button class="btn-small">{{ $r->method === 'cash' ? 'Paid out cash' : 'Mark completed' }}</button></form>
            @endif
        </td>
    </tr>
@empty <tr><td colspan="7" class="muted">No refunds.</td></tr>
@endforelse
</tbody></table></div>
</section>
@endsection
