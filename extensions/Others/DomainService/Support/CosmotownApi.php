<?php

namespace Paymenter\Extensions\Others\DomainService\Support;

use Exception;
use Illuminate\Support\Facades\Http;

/**
 * Every Cosmotown HTTP call lives here.
 *
 * Keeping the transport in one class is what makes a second registrar cheap
 * later: the extension talks domains, this class talks Cosmotown.
 *
 * Requests go through Laravel's Http facade so Paymenter's RequestListener
 * records them under Admin -> HTTP logs when debug mode is on.
 */
class CosmotownApi
{
    private const LIVE_URL = 'https://cosmotown.com/v1/reseller/';

    private const SANDBOX_URL = 'https://sandbox.cosmotown7.com/v1/reseller/';

    public function __construct(
        private string $apiKey,
        private bool $sandbox = false,
    ) {}

    /**
     * Confirm the credentials work. Used by the admin "Test Connection" button.
     */
    public function testConnection(): bool
    {
        $this->get('listdomains');

        return true;
    }

    public function listDomains(?string $domain = null): array
    {
        return $this->get('listdomains', $domain ? ['domain' => $domain] : []);
    }

    /**
     * Returns null when Cosmotown does not know the domain, rather than
     * throwing — callers routinely ask about domains that may not exist yet.
     */
    public function domainInfo(string $domain): ?array
    {
        $response = $this->get('domaininfo', ['domain' => $domain]);

        return isset($response['domain']) ? $response : null;
    }

    /**
     * @param  array  $domains  ['example.com' => 2] — domain => years
     */
    public function registerDomains(array $domains, string $couponId = ''): array
    {
        return $this->post('registerdomains', [
            'coupon_id' => $couponId,
            'items' => $this->items($domains),
        ]);
    }

    /**
     * @param  array  $domains  ['example.com' => 1] — domain => years
     */
    public function renewDomains(array $domains, string $couponId = ''): array
    {
        return $this->post('renewdomains', [
            'coupon_id' => $couponId,
            'items' => $this->items($domains),
        ]);
    }

    /**
     * Start an inbound transfer.
     *
     * The auth code is base64 encoded because Cosmotown expects it that way —
     * sending it raw is accepted and then fails silently at the registry.
     *
     * @param  array  $domains  ['example.com' => 'EPP-CODE']
     */
    public function transferDomains(array $domains): array
    {
        $items = [];

        foreach ($domains as $name => $authCode) {
            $items[] = [
                'name' => $name,
                'authCode' => base64_encode((string) $authCode),
            ];
        }

        return $this->post('transferdomains', ['items' => $items]);
    }

    /**
     * Progress of one or more in-flight transfers.
     *
     * @param  array<string>  $domains
     * @return array<string, array{status: string, message: ?string}>
     */
    public function domainStatus(array $domains): array
    {
        $response = $this->post('domainstatus', ['domains' => array_values($domains)]);

        $statuses = [];

        // The payload is a bare list of per-domain results rather than an
        // object, so walk it instead of indexing by domain.
        foreach ($response as $row) {
            if (!is_array($row) || empty($row['domain'])) {
                continue;
            }

            $statuses[strtolower($row['domain'])] = [
                'status' => (string) ($row['registration_status'] ?? ''),
                'message' => $row['message'] ?? null,
            ];
        }

        return $statuses;
    }

    public function saveNameservers(string $domain, array $nameservers): array
    {
        return $this->post('savedomainnameservers', [
            'domain' => $domain,
            'nameservers' => array_values(array_filter($nameservers)),
        ]);
    }

    /**
     * Change one domain option without clobbering the others.
     *
     * POST domaininfo replaces the whole options object, so sending just
     * lock_domain would silently switch off a customer's WHOIS privacy and
     * auto-billing. Read first, merge, then write.
     *
     * @param  string  $option  enable_private_whois|lock_domain|enable_auto_billing
     */
    public function setDomainOption(string $domain, string $option, bool $value): array
    {
        $current = $this->domainInfo($domain);

        if (!$current) {
            throw new Exception("Cosmotown does not have a record for {$domain}.");
        }

        $options = [
            'enable_private_whois' => (bool) ($current['whois_privacy'] ?? false),
            'lock_domain' => (bool) ($current['locked'] ?? false),
            'enable_auto_billing' => (bool) ($current['auto_billing'] ?? false),
        ];

        $options[$option] = $value;

        return $this->post('domaininfo', [
            'domain' => $domain,
            'options' => $options,
        ]);
    }

    private function items(array $domains): array
    {
        $items = [];

        foreach ($domains as $name => $years) {
            $items[] = [
                'name' => $name,
                'years' => max(1, (int) $years),
            ];
        }

        return $items;
    }

    private function get(string $endpoint, array $query = []): array
    {
        return $this->send('get', $endpoint, $query);
    }

    private function post(string $endpoint, array $payload = []): array
    {
        return $this->send('post', $endpoint, $payload);
    }

    private function send(string $method, string $endpoint, array $data): array
    {
        $url = ($this->sandbox ? self::SANDBOX_URL : self::LIVE_URL) . $endpoint;

        $request = Http::withHeaders([
            'X-API-TOKEN' => $this->apiKey,
            'Accept' => 'application/json',
        ])->timeout(90);

        $response = $method === 'get'
            ? $request->get($url, $data)
            : $request->asJson()->post($url, $data);

        $body = $response->json() ?? [];

        if ($response->failed()) {
            throw new Exception($this->errorMessage($body, $response->status()));
        }

        // Cosmotown can answer 200 while reporting a per-item failure.
        if ($error = $this->embeddedError($body)) {
            throw new Exception($error);
        }

        return $body;
    }

    private function errorMessage(array $body, int $status): string
    {
        $message = $this->embeddedError($body) ?? match (true) {
            $status === 403 => 'Cosmotown rejected the API key.',
            $status === 429 => 'Cosmotown rate limit reached, try again shortly.',
            $status >= 500 => 'Cosmotown is unavailable.',
            default => 'Cosmotown returned an unexpected response.',
        };

        return "{$message} (HTTP {$status})";
    }

    /**
     * Cosmotown is not consistent about where it puts failure text, so check
     * the shapes seen in practice before giving up.
     */
    private function embeddedError(array $body): ?string
    {
        foreach (['error', 'message', 'error_message'] as $key) {
            if (!empty($body[$key]) && is_string($body[$key])) {
                return $body[$key];
            }
        }

        foreach ($body['items'] ?? [] as $item) {
            if (!empty($item['message']) && ($item['status'] ?? null) !== 'SUCCESS') {
                return is_string($item['message']) ? $item['message'] : null;
            }
        }

        return null;
    }
}
