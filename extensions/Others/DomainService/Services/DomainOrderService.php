<?php

namespace Paymenter\Extensions\Others\DomainService\Services;

use App\Exceptions\DisplayException;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\DomainService\Models\Domain;
use Paymenter\Extensions\Others\DomainService\Models\DomainInvoice;
use Paymenter\Extensions\Others\DomainService\Models\DomainTld;

/**
 * Turns a domain registration request into a payable Paymenter invoice.
 *
 * Creates the domain (pending), an invoice priced from the central pricing
 * table, and the link that tells the Paid listener what to do. Everything
 * runs in one transaction, so a half-made order cannot survive an error.
 */
class DomainOrderService
{
    public const RENEW_BEFORE_DAYS = 14;

    /**
     * @return Invoice  The unpaid invoice to send the customer to.
     */
    public function register(User $user, string $name, string $currency, int $years = 1): Invoice
    {
        $name = strtolower(trim($name));
        $currency = strtoupper($currency);

        // Validate the whole name here, not just at the UI, so no caller can
        // push a malformed name through to a registrar.
        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/', $name)) {
            throw new DisplayException('That is not a valid domain name.');
        }

        [$sld, $tldName] = $this->split($name);
        $tld = $this->tld($tldName);
        $pricing = $this->pricing($tld, $currency);
        $years = max($tld->min_years, min($tld->max_years, $years));

        // A domain already held by a live service cannot be sold again.
        if (Domain::where('name', $name)->where('status', '!=', Domain::STATUS_CANCELLED)->exists()) {
            throw new DisplayException("The domain {$name} is already registered with us.");
        }

        return DB::transaction(function () use ($user, $name, $sld, $tldName, $tld, $pricing, $currency, $years) {
            $domain = Domain::create([
                'user_id' => $user->id,
                'registrar_id' => $tld->registrar_id,
                'name' => $name,
                'sld' => $sld,
                'tld' => $tldName,
                'currency' => $currency,
                'status' => Domain::STATUS_PENDING,
                'autorenew' => true,
            ]);

            $invoice = Invoice::create([
                'user_id' => $user->id,
                'currency_code' => $currency,
                'due_at' => now()->addDays(7),
                'status' => 'pending',
            ]);

            $invoice->items()->create([
                'reference_id' => $domain->id,
                'reference_type' => Domain::class,
                'price' => round($pricing->register_price * $years, 2),
                'quantity' => 1,
                'description' => "Domain registration: {$name} ({$years} " . str('year')->plural($years) . ')',
            ]);

            DomainInvoice::create([
                'invoice_id' => $invoice->id,
                'domain_id' => $domain->id,
                'action' => DomainInvoice::ACTION_REGISTER,
                'years' => $years,
            ]);

            return $invoice;
        });
    }

    /** @return array{0: string, 1: string}  [sld, tld] */
    private function split(string $name): array
    {
        $dot = strpos($name, '.');

        if ($dot === false) {
            throw new DisplayException('Enter a full domain name, for example example.com.');
        }

        return [substr($name, 0, $dot), substr($name, $dot + 1)];
    }

    private function tld(string $tldName): DomainTld
    {
        $tld = DomainTld::where('tld', $tldName)->where('enabled', true)->first();

        if (!$tld) {
            throw new DisplayException("We don't currently offer .{$tldName} domains.");
        }

        return $tld;
    }

    private function pricing(DomainTld $tld, string $currency)
    {
        $pricing = $tld->priceFor($currency);

        if (!$pricing) {
            throw new DisplayException(".{$tld->tld} is not available in {$currency}. Please switch currency.");
        }

        return $pricing;
    }
}
