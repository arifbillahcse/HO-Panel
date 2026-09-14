<?php

namespace Paymenter\Extensions\Others\DomainService\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Availability lookups over RDAP.
 *
 * Cosmotown's own API client resolves availability through public RDAP rather
 * than a proprietary endpoint, so this does the same directly: no API key
 * spent, no reseller rate limit consumed, and it keeps working if Cosmotown
 * changes its search route.
 *
 * Deliberately separate from CosmotownApi — RDAP is a registry protocol, not
 * Cosmotown transport, so a second registrar reuses this untouched.
 */
class DomainAvailability
{
    private const RDAP_URL = 'https://rdap.org/domain/';

    /** Availability moves slowly; a short cache absorbs repeated searches. */
    private const CACHE_MINUTES = 10;

    public const AVAILABLE = 'available';

    public const TAKEN = 'taken';

    public const UNKNOWN = 'unknown';

    /**
     * @param  array<string>  $domains
     * @return array<string, string> domain => AVAILABLE|TAKEN|UNKNOWN
     */
    public function check(array $domains): array
    {
        $domains = array_values(array_unique(array_map(
            fn ($d) => strtolower(trim($d)),
            array_filter($domains),
        )));

        $results = [];
        $toLookup = [];

        foreach ($domains as $domain) {
            $cached = Cache::get($this->cacheKey($domain));

            if ($cached) {
                $results[$domain] = $cached;
            } else {
                $toLookup[] = $domain;
            }
        }

        if ($toLookup) {
            $results += $this->lookup($toLookup);
        }

        // Preserve the caller's ordering, which drives the results list.
        $ordered = [];
        foreach ($domains as $domain) {
            $ordered[$domain] = $results[$domain] ?? self::UNKNOWN;
        }

        return $ordered;
    }

    /**
     * @param  array<string>  $domains
     * @return array<string, string>
     */
    private function lookup(array $domains): array
    {
        // One pooled batch rather than sequential requests: checking eight TLDs
        // one at a time would take seconds of a customer's attention.
        $responses = Http::pool(function ($pool) use ($domains) {
            foreach ($domains as $domain) {
                $pool->as($domain)
                    ->timeout(8)
                    ->withHeaders(['Accept' => 'application/rdap+json'])
                    ->get(self::RDAP_URL . $domain);
            }
        });

        $results = [];

        foreach ($domains as $domain) {
            $response = $responses[$domain] ?? null;

            $status = match (true) {
                // A pooled request that fails yields a ConnectionException in
                // place of a Response; report unknown rather than claiming the
                // domain is free and selling something already registered.
                !$response instanceof Response => self::UNKNOWN,
                $response->status() === 404 => self::AVAILABLE,
                $response->successful() => self::TAKEN,
                default => self::UNKNOWN,
            };

            $results[$domain] = $status;

            // Never cache unknown — that is a transient failure, not an answer.
            if ($status !== self::UNKNOWN) {
                Cache::put($this->cacheKey($domain), $status, now()->addMinutes(self::CACHE_MINUTES));
            }
        }

        return $results;
    }

    public function forget(string $domain): void
    {
        Cache::forget($this->cacheKey(strtolower(trim($domain))));
    }

    private function cacheKey(string $domain): string
    {
        return 'cosmotown.available.' . md5($domain);
    }
}
