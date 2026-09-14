<?php

namespace Paymenter\Extensions\Others\DomainService\Contracts;

use Paymenter\Extensions\Others\DomainService\Models\Domain;

/**
 * The one contract every registrar implements.
 *
 * Adding a registrar (ResellerClub, Freenom, …) means writing a class that
 * implements this and nothing else changes: the pricing, ordering, renewal,
 * pages and admin all talk to this interface, never to a specific registrar.
 *
 * capabilities() lets the UI adapt itself — a registrar that cannot return an
 * EPP code advertises eppRetrieval=false and the button hides, rather than
 * every page carrying per-registrar special cases.
 *
 * A driver is constructed with its own credentials (see RegistrarManager), so
 * methods never receive keys as arguments.
 */
interface RegistrarDriver
{
    /**
     * Feature flags the UI and lifecycle read before offering an action.
     *
     * Keys: transfer, eppRetrieval, dns, privacy, lock, autoRenew — each bool.
     *
     * @return array<string, bool>
     */
    public function capabilities(): array;

    /** Confirm the credentials work. Used by the admin "Test connection". */
    public function testConnection(): bool;

    /**
     * Availability for one or more fully-qualified domains.
     *
     * @param  array<string>  $domains
     * @return array<string, string>  domain => available|taken|unknown
     */
    public function checkAvailability(array $domains): array;

    /** Register a new domain for the given number of years. */
    public function register(Domain $domain, int $years): void;

    /** Renew an existing domain by the given number of years. */
    public function renew(Domain $domain, int $years): void;

    /**
     * Current registrar state for a domain.
     *
     * @return array{expires_at: ?string, nameservers: array<string>, locked: bool, privacy: bool}|null
     */
    public function getInfo(Domain $domain): ?array;

    /** @return array<string> */
    public function getNameservers(Domain $domain): array;

    /** @param  array<string>  $nameservers */
    public function saveNameservers(Domain $domain, array $nameservers): void;

    public function setLock(Domain $domain, bool $locked): void;

    public function setPrivacy(Domain $domain, bool $enabled): void;

    /** Refresh expiry, nameservers, lock and privacy from the registrar. */
    public function sync(Domain $domain): void;

    // ---- Declared now, wired in Phase 2 (transfer) ---------------------

    /** Start an inbound transfer using the customer's auth code. */
    public function transfer(Domain $domain, string $authCode, int $years): void;

    /** Progress of an in-flight transfer: pending|complete|failed. */
    public function getTransferStatus(Domain $domain): string;

    /**
     * The domain's EPP/auth code for a transfer out, or null when the
     * registrar has no API for it (e.g. Cosmotown).
     */
    public function getEppCode(Domain $domain): ?string;
}
