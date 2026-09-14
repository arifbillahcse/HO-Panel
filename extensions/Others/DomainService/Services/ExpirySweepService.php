<?php

namespace Paymenter\Extensions\Others\DomainService\Services;

use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DomainService\Models\Domain;
use Paymenter\Extensions\Others\DomainService\Models\DomainTld;
use Throwable;

/**
 * Moves a domain through grace and redemption once it passes its own expiry
 * without being renewed in time.
 *
 * This tracks state locally; it never calls a registrar and never spends
 * money — it only decides which price a renewal invoice should carry next
 * (DomainRenewalService reads the status this sets). A domain that is renewed
 * before expiry never reaches here, since driver.renew() moves expires_at
 * forward and the domain stays active.
 *
 * Chunked and index-backed (status + expires_at) so it stays flat at volume.
 */
class ExpirySweepService
{
    public function sweep(): void
    {
        // Fresh expiries: active domains whose date has passed become grace
        // or redemption immediately, depending on how far past. A missed
        // pre-expiry invoice is the normal way domains land here.
        Domain::query()
            ->whereIn('status', [Domain::STATUS_ACTIVE, Domain::STATUS_GRACE])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById(200, function ($domains) {
                foreach ($domains as $domain) {
                    try {
                        $this->transition($domain);
                    } catch (Throwable $e) {
                        Log::error("DomainService: expiry transition failed for {$domain->name}: " . $e->getMessage());
                    }
                }
            });
    }

    private function transition(Domain $domain): void
    {
        $tld = DomainTld::where('tld', $domain->tld)->first();
        $graceDays = $tld?->grace_days ?? 0;
        $redemptionDays = $tld?->redemption_days ?? 0;

        $daysPast = now()->diffInDays($domain->expires_at, absolute: true);

        $next = match (true) {
            $daysPast <= $graceDays => Domain::STATUS_GRACE,
            $daysPast <= $graceDays + $redemptionDays => Domain::STATUS_REDEMPTION,
            default => Domain::STATUS_EXPIRED,
        };

        if ($next !== $domain->status) {
            $domain->update(['status' => $next]);

            if ($next === Domain::STATUS_EXPIRED) {
                Log::warning("DomainService: {$domain->name} has passed grace and redemption and is now lost.");
            }
        }
    }
}
