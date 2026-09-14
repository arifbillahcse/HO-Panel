<?php

namespace Paymenter\Extensions\Others\DomainService\Services;

use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DomainService\Models\Domain;
use Throwable;

/**
 * Completes inbound transfers.
 *
 * Registrars finish a transfer at the registry hours or days after it starts
 * and send no callback, so this polls. Chunked and index-backed on status so
 * it stays flat at any volume. Only submitted transfers are polled — a row
 * whose auth code is still present has not been sent to the registrar yet.
 */
class TransferPollService
{
    public function sweep(): void
    {
        Domain::query()
            ->where('status', Domain::STATUS_TRANSFER_PENDING)
            ->whereNull('auth_code')
            ->chunkById(200, function ($domains) {
                foreach ($domains as $domain) {
                    try {
                        $this->poll($domain);
                    } catch (Throwable $e) {
                        Log::warning("DomainService: transfer poll failed for {$domain->name}: " . $e->getMessage());
                    }
                }
            });
    }

    public function poll(Domain $domain): void
    {
        $status = $domain->driver()->getTransferStatus($domain);

        if ($status === 'complete') {
            $domain->status = Domain::STATUS_ACTIVE;
            $domain->registered_at ??= now();
            $domain->save();

            try {
                $domain->driver()->sync($domain);
            } catch (Throwable $e) {
                Log::warning("DomainService: transferred {$domain->name} but could not sync: " . $e->getMessage());
            }

            NotificationHelper::sendSystemEmailNotification(
                "Domain transfer completed: {$domain->name}",
                "The inbound transfer of {$domain->name} (domain #{$domain->id}) has completed.",
            );

            return;
        }

        if ($status === 'failed') {
            $domain->update(['status' => Domain::STATUS_TRANSFER_FAILED]);

            NotificationHelper::sendSystemEmailNotification(
                "Domain transfer FAILED: {$domain->name}",
                "The inbound transfer of {$domain->name} (domain #{$domain->id}) failed. "
                . "The customer has paid. Usually the domain is locked at the losing registrar, "
                . "the auth code was wrong, or it was registered within the last 60 days. "
                . "Contact them and restart the transfer once resolved.",
            );
        }
    }
}
