<?php

namespace Paymenter\Extensions\Gateways\EPS;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Every EPS HTTP call lives here.
 *
 * EPS exposes exactly three endpoints — GetToken, InitializeEPS and
 * CheckMerchantTransactionStatus. There is no webhook, no IPN and no refund
 * API, so the extension around this class has to poll. See EPS::reconcile().
 *
 * Requests go through Laravel's Http facade so Paymenter's RequestListener
 * records them under Admin -> HTTP logs when debug mode is on.
 */
class EpsApi
{
    private const LIVE_URL = 'https://pgapi.eps.com.bd';

    private const SANDBOX_URL = 'https://sandboxpgapi.eps.com.bd';

    public function __construct(
        private string $username,
        private string $password,
        private string $hashKey,
        private bool $sandbox = false,
    ) {}

    /**
     * EPS signs one field per call, not the whole body:
     *
     *   GetToken        -> userName
     *   InitializeEPS   -> merchantTransactionId
     *   Verify          -> whichever id you look the transaction up by
     *
     * HMAC-SHA512 over the raw value, keyed with the hash key, base64 encoded.
     */
    public function hash(string $data): string
    {
        return base64_encode(hash_hmac('sha512', $data, $this->hashKey, true));
    }

    /**
     * Bearer token for APIs 02 and 03.
     *
     * EPS returns an expireDate, so the token is cached until just before it
     * lapses rather than re-fetched on every call.
     */
    public function token(): string
    {
        $key = 'eps-token-' . md5($this->baseUrl() . $this->username);

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-hash' => $this->hash($this->username),
        ])->post($this->baseUrl() . '/v1/Auth/GetToken', [
            'userName' => $this->username,
            'password' => $this->password,
        ]);

        $body = $this->decode($response->body());
        $token = $this->get($body, 'token');

        if (!$response->successful() || !$token) {
            throw new Exception('EPS authentication failed: ' . ($this->get($body, 'errorMessage') ?: $response->status()));
        }

        Cache::put($key, $token, $this->tokenLifetime($this->get($body, 'expireDate')));

        return $token;
    }

    /**
     * API 02. Returns the hosted payment page URL the customer is sent to.
     */
    public function initialize(array $payload): string
    {
        $reference = (string) ($payload['merchantTransactionId'] ?? '');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-hash' => $this->hash($reference),
            'Authorization' => 'Bearer ' . $this->token(),
        ])->post($this->baseUrl() . '/v1/EPSEngine/InitializeEPS', $payload);

        $body = $this->decode($response->body());
        $url = $this->get($body, 'RedirectURL');

        if (!$response->successful() || !$url) {
            throw new Exception('EPS could not start the payment: ' . ($this->get($body, 'ErrorMessage') ?: 'HTTP ' . $response->status()));
        }

        return $url;
    }

    /**
     * API 03 — the only trustworthy source of a payment result.
     *
     * Returns null when EPS does not recognise the reference, which happens
     * for a payment the customer abandoned before reaching the bank.
     */
    public function verify(string $merchantTransactionId): ?array
    {
        $response = Http::withHeaders([
            'x-hash' => $this->hash($merchantTransactionId),
            'Authorization' => 'Bearer ' . $this->token(),
        ])->get($this->baseUrl() . '/v1/EPSEngine/CheckMerchantTransactionStatus', [
            'merchantTransactionId' => $merchantTransactionId,
        ]);

        if (!$response->successful()) {
            throw new Exception('EPS verification failed: HTTP ' . $response->status());
        }

        $body = $this->decode($response->body());

        return $this->get($body, 'Status') ? $body : null;
    }

    /**
     * Confirm the credentials work. Used by the admin "Test connection" field.
     */
    public function testConnection(): bool
    {
        return (bool) $this->token();
    }

    /**
     * EPS is inconsistent about casing: GetToken answers in camelCase
     * ("token", "expireDate") while the other two answer in PascalCase
     * ("RedirectURL", "Status"). Look keys up case-insensitively and accept a
     * "data" envelope, which some deployments wrap the payload in.
     *
     * @return mixed
     */
    public function get(array $body, string $key)
    {
        foreach ([$body, $body['data'] ?? [], $body['Data'] ?? []] as $scope) {
            if (!is_array($scope)) {
                continue;
            }

            foreach ($scope as $k => $value) {
                if (strcasecmp((string) $k, $key) === 0 && $value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function baseUrl(): string
    {
        return $this->sandbox ? self::SANDBOX_URL : self::LIVE_URL;
    }

    private function decode(string $body): array
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Trim a minute off the stated expiry so a token can never lapse midway
     * through a payment. Falls back to ten minutes if the date is unparseable.
     */
    private function tokenLifetime($expireDate): int
    {
        if (!$expireDate) {
            return 600;
        }

        try {
            $seconds = (int) now()->diffInSeconds(Carbon::parse($expireDate), absolute: false);
        } catch (Exception) {
            return 600;
        }

        return $seconds > 60 ? min($seconds - 60, 3600) : 600;
    }
}
