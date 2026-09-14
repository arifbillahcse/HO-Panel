<?php

namespace Paymenter\Extensions\Others\DomainService\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DomainService\Models\Domain;
use Paymenter\Extensions\Others\DomainService\Models\DomainInvoice;
use Paymenter\Extensions\Others\DomainService\Models\DomainTld;
use Throwable;

/**
 * Raises renewal invoices before a domain expires, and recovery invoices for
 * one that has already lapsed into grace or redemption.
 *
 * The sweep is chunked and index-backed (status + expires_at) so it stays flat
 * whether there are ten domains or a million. It only ever creates an invoice —
 * the customer pays it, and the Paid listener does the registrar renewal — so
 * nothing here spends money or calls a registrar.
 */
class DomainRenewalService
{
    public function sweep(int $daysAhead = 14): void
    {
        // Active domains approaching expiry, invoiced ahead of time at the
        // normal renew price.
        Domain::query()
            ->where('status', Domain::STATUS_ACTIVE)
            ->where('autorenew', true)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($daysAhead)])
            ->chunkById(200, function ($domains) {
                foreach ($domains as $domain) {
                    try {
                        $this->createRenewalInvoice($domain);
                    } catch (Throwable $e) {
                        Log::error("DomainService: could not raise renewal for {$domain->name}: " . $e->getMessage());
                    }
                }
            });

        // Grace and redemption domains are already past due — always due an
        // invoice, whatever autorenew says, since the customer must act to
        // keep the domain at all. ExpirySweepService is what puts a domain in
        // either status; this only prices and invoices it.
        Domain::query()
            ->whereIn('status', [Domain::STATUS_GRACE, Domain::STATUS_REDEMPTION])
            ->chunkById(200, function ($domains) {
                foreach ($domains as $domain) {
                    try {
                        $this->createRenewalInvoice($domain);
                    } catch (Throwable $e) {
                        Log::error("DomainService: could not raise recovery invoice for {$domain->name}: " . $e->getMessage());
                    }
                }
            });
    }

    /**
     * @return Invoice|null  Null when a renewal invoice is already outstanding.
     */
    public function createRenewalInvoice(Domain $domain, int $years = 1): ?Invoice
    {
        // Never stack renewal invoices: if one is already unpaid, leave it.
        $hasOpen = DomainInvoice::where('domain_id', $domain->id)
            ->where('action', DomainInvoice::ACTION_RENEW)
            ->whereHas('invoice', fn ($q) => $q->where('status', 'pending'))
            ->exists();

        if ($hasOpen) {
            return null;
        }

        $tld = DomainTld::where('tld', $domain->tld)->first();
        $pricing = $tld?->priceFor((string) $domain->currency);

        if (!$pricing) {
            Log::warning("DomainService: no {$domain->currency} renewal price for .{$domain->tld}; skipping {$domain->name}.");

            return null;
        }

        $inRedemption = $domain->status === Domain::STATUS_REDEMPTION;
        $unitPrice = $inRedemption ? $pricing->redemptionRenewPrice() : (float) $pricing->renew_price;

        $description = $inRedemption
            ? "Domain recovery: {$domain->name} — past due, includes a redemption fee ({$years} " . str('year')->plural($years) . ')'
            : "Domain renewal: {$domain->name} ({$years} " . str('year')->plural($years) . ')';

        return DB::transaction(function () use ($domain, $unitPrice, $years, $description) {
            $invoice = Invoice::create([
                'user_id' => $domain->user_id,
                'currency_code' => $domain->currency,
                'due_at' => $domain->expires_at ?? now()->addDays(7),
                'status' => 'pending',
            ]);

            $invoice->items()->create([
                'reference_id' => $domain->id,
                'reference_type' => Domain::class,
                'price' => round($unitPrice * $years, 2),
                'quantity' => 1,
                'description' => $description,
            ]);

            DomainInvoice::create([
                'invoice_id' => $invoice->id,
                'domain_id' => $domain->id,
                'action' => DomainInvoice::ACTION_RENEW,
                'years' => $years,
            ]);

            return $invoice;
        });
    }
}
