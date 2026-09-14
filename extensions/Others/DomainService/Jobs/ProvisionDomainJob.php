<?php

namespace Paymenter\Extensions\Others\DomainService\Jobs;

use App\Helpers\NotificationHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DomainService\Models\Domain;
use Paymenter\Extensions\Others\DomainService\Models\DomainInvoice;
use Throwable;

/**
 * Carries out the registrar side of a paid domain invoice.
 *
 * Queued so a slow registrar API never holds up the payment request, and so
 * it scales — at volume this is where a worker pool does the work. Marked
 * processed only on success, so a failure can be retried without being lost;
 * registrations are made idempotent by checking status first, so a retry of
 * an already-registered domain re-syncs rather than double-registering.
 */
class ProvisionDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $domainInvoiceId) {}

    public function handle(): void
    {
        $link = DomainInvoice::with('domain')->find($this->domainInvoiceId);

        if (!$link || $link->processed || !$link->domain) {
            return;
        }

        $domain = $link->domain;
        $driver = $domain->driver();
        $years = max(1, (int) $link->years);

        try {
            switch ($link->action) {
                case DomainInvoice::ACTION_REGISTER:
                    // Idempotent: only register a domain that is still pending.
                    if ($domain->status === Domain::STATUS_PENDING) {
                        $driver->register($domain, $years);
                    }
                    $driver->sync($domain); // sets active, expiry, nameservers
                    break;

                case DomainInvoice::ACTION_RENEW:
                    $driver->renew($domain, $years);
                    $driver->sync($domain); // pulls the new expiry from the registrar

                    // sync() only promotes STATUS_PENDING to active; a domain
                    // paid back from grace or redemption needs it set directly,
                    // since it was never pending.
                    if (in_array($domain->fresh()->status, Domain::LAPSED_STATUSES, true)) {
                        $domain->update(['status' => Domain::STATUS_ACTIVE]);
                    }
                    break;

                case DomainInvoice::ACTION_TRANSFER:
                    // Submit the transfer once. The auth code's presence marks
                    // "not yet submitted", so a retry after submission does not
                    // resubmit; the status sweep completes it later.
                    if ($domain->status === Domain::STATUS_TRANSFER_PENDING && filled($domain->auth_code)) {
                        $driver->transfer($domain, (string) $domain->auth_code, $years);
                        // Single-use and worthless once submitted — do not keep it.
                        $domain->update(['auth_code' => null]);
                    }
                    break;
            }

            $link->update(['processed' => true]);
        } catch (Throwable $e) {
            $message = "Domain {$link->action} failed for {$domain->name} "
                . "(invoice #{$link->invoice_id}): " . $e->getMessage()
                . "\n\nThe customer has paid. Resolve it at the registrar or retry.";

            Log::error('DomainService: ' . $message);
            NotificationHelper::sendSystemEmailNotification("Domain {$link->action} failed: {$domain->name}", $message);

            throw $e; // let the queue retry within $tries
        }
    }
}
