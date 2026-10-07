<?php

declare(strict_types=1);

namespace App\Domain\Operations;

use App\Domain\Audit;
use App\Domain\Hotel;
use App\Domain\Ids;
use App\Domain\Money;
use Illuminate\Support\Facades\DB;

/**
 * P25 — fiscal documents behind a provider boundary. The bundled provider
 * is a clearly labelled simulator ("SIM-" numbers, never a tax invoice).
 * Retries always reuse the same request_reference so a provider can
 * de-duplicate; uncertain outcomes stay visible until resolved.
 */
final class FiscalService
{
    public function __construct(private readonly JobRunner $jobs) {}

    public function provider(): string
    {
        return (string) config('services.fiscal.provider', 'simulator');
    }

    public function queueInvoice(string $checkoutId): void
    {
        if (DB::table('fiscal_documents')->where('checkout_id', $checkoutId)->where('kind', 'invoice')->exists()) {
            return;
        }
        $c = DB::table('checkouts')->where('id', $checkoutId)->first();
        $lines = DB::table('charge_allocations')->join('charges', 'charges.id', '=', 'charge_allocations.charge_id')
            ->join('order_items', 'order_items.id', '=', 'charges.order_item_id')->where('charge_allocations.checkout_id', $checkoutId)
            ->get(['order_items.meal_name', 'order_items.quantity', 'charge_allocations.amount_minor'])
            ->map(static fn ($l) => ['description' => $l->meal_name, 'quantity' => (int) $l->quantity, 'amount_minor' => (int) $l->amount_minor])->all();
        $rate = $c->tax_rate_basis_points;
        $id = Ids::new();
        DB::table('fiscal_documents')->insert([
            'id' => $id, 'kind' => 'invoice', 'checkout_id' => $checkoutId, 'request_reference' => 'INV-'.$checkoutId,
            'payload' => json_encode(['receipt' => $c->receipt_number, 'lines' => $lines, 'tax_rate_basis_points' => $rate,
                'tax_minor' => $rate === null ? null : Money::includedTax((int) $c->amount_minor, (int) $rate)], JSON_THROW_ON_ERROR),
            'total_minor' => (int) $c->amount_minor, 'state' => 'pending', 'provider' => $this->provider(), 'attempts' => 0,
            'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        $this->dispatchOrSubmit($id);
    }

    private function dispatchOrSubmit(string $id): void
    {
        // The simulator is local and deterministic, so its number can be on the first receipt print.
        if ($this->provider() === 'simulator') {
            $this->submit($id);

            return;
        }
        $this->jobs->dispatch('fiscal_submit', 'fiscal:'.$id, ['document_id' => $id]);
    }

    public function queueCreditNote(string $refundId): void
    {
        $r = DB::table('refunds')->join('payments', 'payments.id', '=', 'refunds.payment_id')->where('refunds.id', $refundId)->first(['refunds.*', 'payments.checkout_id']);
        $invoice = DB::table('fiscal_documents')->where('checkout_id', $r->checkout_id)->where('kind', 'invoice')->first();
        $id = Ids::new();
        DB::table('fiscal_documents')->insert([
            'id' => $id, 'kind' => 'credit_note', 'checkout_id' => $r->checkout_id, 'refund_id' => $refundId, 'related_document_id' => $invoice?->id,
            'request_reference' => 'CRN-'.$refundId, 'payload' => json_encode(['reason' => $r->reason, 'amount_minor' => (int) $r->amount_minor, 'related' => $invoice?->provider_document_number], JSON_THROW_ON_ERROR),
            'total_minor' => (int) $r->amount_minor, 'state' => 'pending', 'provider' => $this->provider(), 'attempts' => 0,
            'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        $this->dispatchOrSubmit($id);
    }

    /** @return bool true when finished (accepted or permanently failed) */
    public function submit(string $documentId): bool
    {
        $doc = DB::table('fiscal_documents')->where('id', $documentId)->first();
        if ($doc === null || in_array($doc->state, ['accepted'], true)) {
            return true;
        }
        DB::table('fiscal_documents')->where('id', $documentId)->update(['attempts' => (int) $doc->attempts + 1, 'state' => 'submitted', 'updated_at' => now('UTC')]);
        if ($doc->provider === 'simulator') {
            $prefix = $doc->kind === 'invoice' ? 'SIM-INV-' : 'SIM-CRN-';
            $seq = DB::table('fiscal_documents')->where('kind', $doc->kind)->where('state', 'accepted')->count() + 1;
            DB::table('fiscal_documents')->where('id', $documentId)->update([
                'state' => 'accepted', 'provider_reference' => 'SIMREF-'.substr(hash('sha256', $doc->request_reference), 0, 12),
                'provider_document_number' => $prefix.now(Hotel::timezone())->format('Y').'-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
                'last_error' => null, 'updated_at' => now('UTC'),
            ]);

            return true;
        }
        DB::table('fiscal_documents')->where('id', $documentId)->update(['state' => 'failed', 'last_error' => 'No eTIMS adapter is configured on this installation.', 'updated_at' => now('UTC')]);

        return false;
    }

    public function retry(string $documentId, string $actorId): void
    {
        DB::table('fiscal_documents')->where('id', $documentId)->whereIn('state', ['failed', 'uncertain', 'pending'])->update(['state' => 'pending', 'updated_at' => now('UTC')]);
        $this->jobs->dispatch('fiscal_submit', 'fiscal:'.$documentId.':'.time(), ['document_id' => $documentId]);
        Audit::record('fiscal_retry_requested', $actorId, ['document_id' => $documentId]);
    }

    /** @return list<object> */
    public function recent(): array
    {
        return DB::table('fiscal_documents')->orderByDesc('created_at')->limit(100)->get()->all();
    }
}
