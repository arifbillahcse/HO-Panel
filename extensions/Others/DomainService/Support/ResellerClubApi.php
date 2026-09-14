<?php

namespace Paymenter\Extensions\Others\DomainService\Support;

use Exception;
use Illuminate\Support\Facades\Http;

/**
 * ResellerClub / LogicBoxes HTTP API client.
 *
 * The API is GET-based; auth is auth-userid (reseller id) + api-key on every
 * call. Responses are JSON. Endpoints and parameter names here follow the
 * documented LogicBoxes HTTP API — verify against current docs and OT&E before
 * live use.
 */
class ResellerClubApi
{
    private const LIVE_URL = 'https://httpapi.com/api/';

    private const TEST_URL = 'https://test.httpapi.com/api/';

    public function __construct(
        private string $resellerId,
        private string $apiKey,
        private bool $sandbox = false,
    ) {}

    public function available(string $sld, string $tld): string
    {
        $response = $this->get('domains/available.json', [
            'domain-name' => $sld,
            'tlds' => $tld,
        ]);

        $status = $response["{$sld}.{$tld}"]['status'] ?? 'unknown';

        return match ($status) {
            'available' => 'available',
            'regthroughothers', 'regthroughus', 'unknown' => $status === 'unknown' ? 'unknown' : 'taken',
            default => 'taken',
        };
    }

    public function orderId(string $domain): ?string
    {
        $response = $this->get('domains/orderid.json', ['domain-name' => $domain]);

        return is_scalar($response) ? (string) $response : null;
    }

    /** @return array<string, mixed>|null */
    public function detailsByName(string $domain): ?array
    {
        $response = $this->get('domains/details-by-name.json', [
            'domain-name' => $domain,
            'options' => 'All',
        ]);

        return isset($response['orderid']) ? $response : null;
    }

    /** @param  array<string>  $nameservers */
    public function register(string $domain, int $years, array $nameservers, array $contacts, string $customerId): array
    {
        return $this->get('domains/register.json', array_merge([
            'domain-name' => $domain,
            'years' => $years,
            'customer-id' => $customerId,
            'reg-contact-id' => $contacts['reg'],
            'admin-contact-id' => $contacts['admin'],
            'tech-contact-id' => $contacts['tech'],
            'billing-contact-id' => $contacts['billing'],
            'invoice-option' => 'NoInvoice',
            'protect-privacy' => 'false',
        ], $this->nsParams($nameservers)));
    }

    public function renew(string $orderId, int $years, int $expEpoch): array
    {
        return $this->get('domains/renew.json', [
            'order-id' => $orderId,
            'years' => $years,
            'exp-date' => $expEpoch,
            'invoice-option' => 'NoInvoice',
        ]);
    }

    public function transfer(string $domain, string $authCode, array $contacts, string $customerId): array
    {
        return $this->get('domains/transfer.json', [
            'domain-name' => $domain,
            'auth-code' => $authCode,
            'customer-id' => $customerId,
            'reg-contact-id' => $contacts['reg'],
            'admin-contact-id' => $contacts['admin'],
            'tech-contact-id' => $contacts['tech'],
            'billing-contact-id' => $contacts['billing'],
            'invoice-option' => 'NoInvoice',
        ]);
    }

    /** @param  array<string>  $nameservers */
    public function modifyNameservers(string $orderId, array $nameservers): array
    {
        return $this->get('domains/modify-ns.json', array_merge(
            ['order-id' => $orderId],
            $this->nsParams($nameservers),
        ));
    }

    public function setLock(string $orderId, bool $locked): array
    {
        $endpoint = $locked ? 'domains/enable-theft-protection.json' : 'domains/disable-theft-protection.json';

        return $this->get($endpoint, ['order-id' => $orderId]);
    }

    public function setPrivacy(string $orderId, bool $enabled): array
    {
        return $this->get('domains/modify-privacy-protection.json', [
            'order-id' => $orderId,
            'protect-privacy' => $enabled ? 'true' : 'false',
            'reason' => 'Customer request',
        ]);
    }

    /** Existing customer id by email, or null. */
    public function customerId(string $email): ?string
    {
        try {
            $response = $this->get('customers/details.json', ['username' => $email]);
        } catch (Exception) {
            return null; // ResellerClub 404s when the customer does not exist.
        }

        return isset($response['customerid']) ? (string) $response['customerid'] : null;
    }

    public function signupCustomer(array $profile): string
    {
        $response = $this->get('customers/v2/signup.json', $profile);

        if (!is_scalar($response)) {
            throw new Exception('ResellerClub did not return a customer id on signup.');
        }

        return (string) $response;
    }

    public function addContact(array $contact): string
    {
        $response = $this->get('contacts/add.json', $contact);

        if (!is_scalar($response)) {
            throw new Exception('ResellerClub did not return a contact id.');
        }

        return (string) $response;
    }

    public function testConnection(): bool
    {
        // A cheap authenticated call; throws on bad credentials.
        $this->get('domains/available.json', ['domain-name' => 'example', 'tlds' => 'com']);

        return true;
    }

    /** @param  array<string>  $nameservers */
    private function nsParams(array $nameservers): array
    {
        $params = [];
        foreach (array_values(array_filter($nameservers)) as $ns) {
            $params['ns'][] = $ns; // repeated ns= parameters
        }

        return $params;
    }

    private function get(string $endpoint, array $params): mixed
    {
        $response = Http::asJson()
            ->timeout(90)
            ->get($this->baseUrl() . $endpoint, array_merge([
                'auth-userid' => $this->resellerId,
                'api-key' => $this->apiKey,
            ], $params));

        $body = $response->json();

        if ($response->failed()) {
            $message = is_array($body) ? ($body['message'] ?? 'request failed') : 'request failed';
            throw new Exception("ResellerClub: {$message} (HTTP {$response->status()})");
        }

        // ResellerClub reports per-call errors with status "error" + message.
        if (is_array($body) && ($body['status'] ?? null) === 'error') {
            throw new Exception('ResellerClub: ' . ($body['message'] ?? 'unknown error'));
        }

        return $body;
    }

    private function baseUrl(): string
    {
        return $this->sandbox ? self::TEST_URL : self::LIVE_URL;
    }
}
