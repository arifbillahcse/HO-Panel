<?php

namespace Paymenter\Extensions\Others\DomainService\Drivers;

use Exception;
use Paymenter\Extensions\Others\DomainService\Contracts\RegistrarDriver;
use Paymenter\Extensions\Others\DomainService\Models\Domain;
use Paymenter\Extensions\Others\DomainService\Support\DomainAvailability;
use Paymenter\Extensions\Others\DomainService\Support\ResellerClubApi;

/**
 * ResellerClub / LogicBoxes as a RegistrarDriver.
 *
 * ResellerClub is a very different registrar from Cosmotown — the customer is
 * the WHOIS registrant, so registration needs a ResellerClub customer and four
 * contacts, and every post-registration call keys off an order id rather than
 * the domain name. All of that is absorbed here: the module above still calls
 * register(, ) and nothing else changes. That is the whole point
 * of the driver contract.
 *
 * The ResellerClub customer id is cached as a user property, the order id in
 * the domain's registrar_ref. Untested scaffold — verify against ResellerClub's
 * current API docs and OT&E before live use.
 */
class ResellerClubDriver implements RegistrarDriver
{
    private ResellerClubApi $api;

    public function __construct(array $credentials, bool $sandbox = false)
    {
        $this->api = new ResellerClubApi(
            (string) ($credentials['reseller_id'] ?? ''),
            (string) ($credentials['api_key'] ?? ''),
            $sandbox,
        );
    }

    public function capabilities(): array
    {
        return [
            'transfer' => true,
            'eppRetrieval' => true,   // details-by-name returns the auth code
            'dns' => true,
            'dnsRecords' => true,     // LogicBoxes' separate DNS Management product.
            'privacy' => true,
            'lock' => true,
            'autoRenew' => false,
        ];
    }

    public function testConnection(): bool
    {
        return $this->api->testConnection();
    }

    public function checkAvailability(array $domains): array
    {
        // RDAP is registrar-neutral and free, so reuse it rather than spending
        // ResellerClub availability calls.
        return (new DomainAvailability)->check($domains);
    }

    public function register(Domain $domain, int $years): void
    {
        $customerId = $this->customerId($domain);
        $contacts = $this->contacts($domain, $customerId);

        $result = $this->api->register($domain->name, max(1, $years), [], $contacts, $customerId);

        if ($orderId = ($result['entityid'] ?? null)) {
            $domain->update(['registrar_ref' => (string) $orderId]);
        }
    }

    public function renew(Domain $domain, int $years): void
    {
        $orderId = $this->orderId($domain);
        $exp = $domain->expires_at?->timestamp ?? now()->timestamp;

        $this->api->renew($orderId, max(1, $years), $exp);
    }

    public function getInfo(Domain $domain): ?array
    {
        $details = $this->api->detailsByName($domain->name);

        if (!$details) {
            return null;
        }

        $nameservers = [];
        foreach ($details as $key => $value) {
            if (str_starts_with((string) $key, 'ns') && is_string($value) && $value !== '') {
                $nameservers[] = $value;
            }
        }

        return [
            'expires_at' => isset($details['endtime']) ? date('Y-m-d', (int) $details['endtime']) : null,
            'nameservers' => $nameservers,
            'locked' => in_array('transferlock', (array) ($details['orderstatus'] ?? []), true),
            'privacy' => ($details['isprivacyprotected'] ?? 'false') === 'true',
        ];
    }

    public function getNameservers(Domain $domain): array
    {
        return $this->getInfo($domain)['nameservers'] ?? [];
    }

    public function saveNameservers(Domain $domain, array $nameservers): void
    {
        $this->api->modifyNameservers($this->orderId($domain), $nameservers);
    }

    public function setLock(Domain $domain, bool $locked): void
    {
        $this->api->setLock($this->orderId($domain), $locked);
    }

    public function setPrivacy(Domain $domain, bool $enabled): void
    {
        $this->api->setPrivacy($this->orderId($domain), $enabled);
    }

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

    public function transfer(Domain $domain, string $authCode, int $years): void
    {
        $customerId = $this->customerId($domain);
        $contacts = $this->contacts($domain, $customerId);

        $result = $this->api->transfer($domain->name, $authCode, $contacts, $customerId);

        if ($orderId = ($result['entityid'] ?? null)) {
            $domain->update(['registrar_ref' => (string) $orderId]);
        }
    }

    public function getTransferStatus(Domain $domain): string
    {
        $details = $this->api->detailsByName($domain->name);

        $status = strtolower((string) ($details['actionstatus'] ?? $details['currentstatus'] ?? ''));

        return match (true) {
            str_contains($status, 'success'), $status === 'active' => 'complete',
            str_contains($status, 'fail'), str_contains($status, 'cancel') => 'failed',
            default => 'pending',
        };
    }

    public function getEppCode(Domain $domain): ?string
    {
        $details = $this->api->detailsByName($domain->name);

        return $details['domsecret'] ?? null;
    }

    // ---- DNS zone records --------------------------------------------
    // Requires LogicBoxes' DNS Management product to be enabled on the
    // account — a separate product from domain reseller. See
    // Support/ResellerClubApi for the endpoint caveat.

    public function getDnsRecords(Domain $domain): array
    {
        $records = $this->api->searchDnsRecords($domain->name);

        return array_map(fn ($r) => [
            'type' => (string) ($r['type'] ?? ''),
            'host' => (string) ($r['name'] ?? $r['host'] ?? ''),
            'value' => (string) ($r['value'] ?? ''),
            'ttl' => (int) ($r['timetolive'] ?? $r['ttl'] ?? 3600),
        ], $records);
    }

    public function addDnsRecord(Domain $domain, array $record): void
    {
        $this->api->addDnsRecord($domain->name, $record);
    }

    public function deleteDnsRecord(Domain $domain, array $record): void
    {
        $this->api->deleteDnsRecord($domain->name, $record);
    }

    // ---- ResellerClub-specific plumbing, private to this driver ----------

    /** The ResellerClub order id for this domain, resolved and cached. */
    private function orderId(Domain $domain): string
    {
        if (filled($domain->registrar_ref)) {
            return (string) $domain->registrar_ref;
        }

        $orderId = $this->api->orderId($domain->name);

        if (!$orderId) {
            throw new Exception("ResellerClub has no order for {$domain->name}.");
        }

        $domain->update(['registrar_ref' => $orderId]);

        return $orderId;
    }

    /** A ResellerClub customer for the domain's owner, created once and cached. */
    private function customerId(Domain $domain): string
    {
        $user = $domain->user;
        $existing = $user->properties()->where('key', 'resellerclub_customer_id')->value('value');

        if ($existing) {
            return (string) $existing;
        }

        $props = $user->properties()->pluck('value', 'key')->toArray();

        $id = $this->api->customerId($user->email) ?? $this->api->signupCustomer([
            'username' => $user->email,
            'passwd' => bin2hex(random_bytes(8)) . 'A1!',
            'name' => trim($user->name) ?: $user->email,
            'company' => $props['company_name'] ?? 'N/A',
            'address-line-1' => $props['address'] ?? 'N/A',
            'city' => $props['city'] ?? 'N/A',
            'state' => $props['state'] ?? 'N/A',
            'country' => strtoupper($props['country'] ?? 'US'),
            'zipcode' => $props['zip'] ?? '00000',
            'phone-cc' => '1',
            'phone' => preg_replace('/\D+/', '', (string) ($props['phone'] ?? '0000000000')),
            'lang-pref' => 'en',
        ]);

        $user->properties()->updateOrCreate(
            ['key' => 'resellerclub_customer_id'],
            ['name' => 'ResellerClub customer id', 'value' => $id],
        );

        return $id;
    }

    /** One contact reused for all four roles. */
    private function contacts(Domain $domain, string $customerId): array
    {
        $user = $domain->user;
        $props = $user->properties()->pluck('value', 'key')->toArray();

        $contactId = $this->api->addContact([
            'name' => trim($user->name) ?: $user->email,
            'company' => $props['company_name'] ?? 'N/A',
            'email' => $user->email,
            'address-line-1' => $props['address'] ?? 'N/A',
            'city' => $props['city'] ?? 'N/A',
            'state' => $props['state'] ?? 'N/A',
            'country' => strtoupper($props['country'] ?? 'US'),
            'zipcode' => $props['zip'] ?? '00000',
            'phone-cc' => '1',
            'phone' => preg_replace('/\D+/', '', (string) ($props['phone'] ?? '0000000000')),
            'customer-id' => $customerId,
            'type' => 'Contact',
        ]);

        return ['reg' => $contactId, 'admin' => $contactId, 'tech' => $contactId, 'billing' => $contactId];
    }
}
