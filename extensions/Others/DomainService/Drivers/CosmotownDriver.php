<?php

namespace Paymenter\Extensions\Others\DomainService\Drivers;

use Exception;
use Paymenter\Extensions\Others\DomainService\Contracts\RegistrarDriver;
use Paymenter\Extensions\Others\DomainService\Models\Domain;
use Paymenter\Extensions\Others\DomainService\Support\CosmotownApi;
use Paymenter\Extensions\Others\DomainService\Support\DomainAvailability;

/**
 * Cosmotown as a RegistrarDriver.
 *
 * All Cosmotown-specific knowledge lives here and in Support/CosmotownApi.
 * The module above never mentions Cosmotown — swap in another driver and
 * nothing else changes.
 */
class CosmotownDriver implements RegistrarDriver
{
    private CosmotownApi $api;

    /**
     * @param  array<string, mixed>  $credentials  Stored on the registrar row.
     */
    public function __construct(array $credentials, bool $sandbox = false)
    {
        $this->api = new CosmotownApi((string) ($credentials['apikey'] ?? ''), $sandbox);
    }

    public function capabilities(): array
    {
        return [
            'transfer' => true,
            'eppRetrieval' => false, // Cosmotown has no reseller API for this.
            'dns' => true,
            'dnsRecords' => false,   // No zone/record API in the reseller product.
            'privacy' => true,
            'lock' => true,
            'autoRenew' => false,    // The module drives renewals, not the registrar.
        ];
    }

    public function testConnection(): bool
    {
        return $this->api->testConnection();
    }

    public function checkAvailability(array $domains): array
    {
        // DomainAvailability already answers available|taken|unknown.
        return (new DomainAvailability)->check($domains);
    }

    public function register(Domain $domain, int $years): void
    {
        $this->api->registerDomains([$domain->name => max(1, $years)]);
    }

    public function renew(Domain $domain, int $years): void
    {
        $this->api->renewDomains([$domain->name => max(1, $years)]);
    }

    public function getInfo(Domain $domain): ?array
    {
        $info = $this->api->domainInfo($domain->name);

        if (!$info) {
            return null;
        }

        return [
            'expires_at' => $info['expiration_date'] ?? null,
            'nameservers' => array_values($info['nameservers'] ?? []),
            'locked' => (bool) ($info['locked'] ?? false),
            'privacy' => (bool) ($info['whois_privacy'] ?? false),
        ];
    }

    public function getNameservers(Domain $domain): array
    {
        return $this->getInfo($domain)['nameservers'] ?? [];
    }

    public function saveNameservers(Domain $domain, array $nameservers): void
    {
        $this->api->saveNameservers($domain->name, $nameservers);
    }

    public function setLock(Domain $domain, bool $locked): void
    {
        $this->api->setDomainOption($domain->name, 'lock_domain', $locked);
    }

    public function setPrivacy(Domain $domain, bool $enabled): void
    {
        $this->api->setDomainOption($domain->name, 'enable_private_whois', $enabled);
    }

    /**
     * Pull current registrar state onto the domain row. Used after
     * registration and by the sync sweep. Persists nothing the registrar
     * does not confirm.
     */
    public function sync(Domain $domain): void
    {
        $info = $this->getInfo($domain);

        if (!$info) {
            return;
        }

        if ($info['expires_at']) {
            $domain->expires_at = $info['expires_at'];
        }

        $domain->nameservers = $info['nameservers'];
        $domain->locked = $info['locked'];
        $domain->privacy = $info['privacy'];

        if ($domain->status === Domain::STATUS_PENDING) {
            $domain->status = Domain::STATUS_ACTIVE;
            $domain->registered_at ??= now();
        }

        $domain->save();
    }

    // ---- Transfer: API is ready; the module wires these in Phase 2 -------

    public function transfer(Domain $domain, string $authCode, int $years): void
    {
        $this->api->transferDomains([$domain->name => $authCode]);
    }

    public function getTransferStatus(Domain $domain): string
    {
        $statuses = $this->api->domainStatus([$domain->name]);
        $status = strtoupper((string) ($statuses[strtolower($domain->name)]['status'] ?? ''));

        return match ($status) {
            'COMPLETE' => 'complete',
            'FAILED', 'ERROR', 'CANCELLED' => 'failed',
            default => 'pending',
        };
    }

    public function getEppCode(Domain $domain): ?string
    {
        // Cosmotown exposes no reseller endpoint for this — the panel must
        // fetch it by hand. capabilities()['eppRetrieval'] is false so the UI
        // never offers it.
        return null;
    }

    // ---- DNS zone records: not offered by Cosmotown's reseller API -------
    // capabilities()['dnsRecords'] is false, so the UI never calls these; they
    // throw rather than silently no-op if something ever does.

    public function getDnsRecords(Domain $domain): array
    {
        throw new Exception('DNS record management is not available for domains at this registrar.');
    }

    public function addDnsRecord(Domain $domain, array $record): void
    {
        throw new Exception('DNS record management is not available for domains at this registrar.');
    }

    public function deleteDnsRecord(Domain $domain, array $record): void
    {
        throw new Exception('DNS record management is not available for domains at this registrar.');
    }
}
