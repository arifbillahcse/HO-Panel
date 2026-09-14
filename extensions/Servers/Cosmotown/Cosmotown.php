<?php

namespace Paymenter\Extensions\Servers\Cosmotown;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Server;
use App\Events\Invoice\Paid;
use App\Helpers\ExtensionHelper;
use App\Helpers\NotificationHelper;
use App\Models\Service;
use App\Rules\Domain as DomainRule;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Paymenter\Extensions\Servers\Cosmotown\Livewire\Domains;
use Paymenter\Extensions\Servers\Cosmotown\Livewire\DomainSearch;

#[ExtensionMeta(
    name: 'Cosmotown',
    description: 'Register and renew domain names through Cosmotown',
    version: '1.0.0',
    author: 'Hostorio',
    url: 'https://cosmotown.com',
    icon: 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgd2lkdGg9IjI0IiBoZWlnaHQ9IjI0Ij48ZyBmaWxsPSJub25lIiBzdHJva2U9IndoaXRlIiBzdHJva2Utd2lkdGg9IjEuOCIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSI5Ii8+PGVsbGlwc2UgY3g9IjEyIiBjeT0iMTIiIHJ4PSI0IiByeT0iOSIvPjxwYXRoIGQ9Ik0zLjUgOWgxN00zLjUgMTVoMTciLz48L2c+PC9zdmc+',
)]
class Cosmotown extends Server
{
    /**
     * Set once a domain has actually been registered. The renewal listener
     * keys off this to tell a renewal invoice apart from the very first one,
     * which is settled before createServer() has run.
     */
    private const REGISTERED_KEY = 'cosmotown_registered_at';

    private const DOMAIN_KEY = 'domain';

    /** Invoice id of the last renewal actually sent to Cosmotown. */
    private const LAST_RENEWAL_KEY = 'cosmotown_last_renewal_invoice';

    private const AUTH_CODE_KEY = 'auth_code';

    /** pending | complete | failed — only set on transfer orders. */
    private const TRANSFER_KEY = 'cosmotown_transfer_status';

    public function boot()
    {
        require __DIR__ . '/routes/web.php';

        View::addNamespace('cosmotown', __DIR__ . '/resources/views');

        Livewire::component('cosmotown-domain-search', DomainSearch::class);
        Livewire::component('cosmotown-domains-index', Domains\Index::class);
        Livewire::component('cosmotown-domains-show', Domains\Show::class);

        // Put Domains in the storefront nav beside Shop.
        Event::listen('navigation', function () {
            return [
                'name' => 'Domains',
                'url' => route('cosmotown.search'),
                'icon' => 'ri-global',
                'priority' => 20,
            ];
        });

        // And in the client sidebar, between Services (20) and Invoices (30).
        Event::listen('navigation.dashboard', function () {
            return [
                'name' => 'Domains',
                'url' => route('cosmotown.domains'),
                'icon' => 'ri-global',
                'condition' => Auth::check(),
                'priority' => 25,
            ];
        });

        // Transfers finish at the registry hours or days after they start and
        // Cosmotown sends no callback, so poll. Console-only: boot() also runs
        // on web requests, where registering a schedule is pure overhead.
        if (app()->runningInConsole()) {
            Schedule::call(function () {
                Service::query()
                    ->whereHas('product.server', fn ($query) => $query->where('extension', 'Cosmotown'))
                    ->whereHas('properties', fn ($query) => $query
                        ->where('key', self::TRANSFER_KEY)
                        ->where('value', 'pending'))
                    ->get()
                    ->each(fn (Service $service) => ExtensionHelper::callService(
                        $service,
                        'checkTransfer',
                        mayFail: true,
                    ));
            })->name('cosmotown-transfer-poll')->hourly()->withoutOverlapping();
        }

        Event::listen(Paid::class, function (Paid $event) {
            foreach ($event->invoice->items as $item) {
                if ($item->reference_type !== Service::class) {
                    continue;
                }

                $service = $item->reference;

                if (!$service || !$this->ownsService($service)) {
                    continue;
                }

                // Delegated rather than called directly: only one instance of
                // this extension is booted, so its credentials may belong to a
                // different Cosmotown server row than this service uses.
                // callService() resolves the right one.
                ExtensionHelper::callService(
                    $service,
                    'renewDomain',
                    [$event->invoice->id],
                    mayFail: true,
                );
            }
        });
    }

    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'apikey',
                'type' => 'password',
                'label' => 'Reseller API key',
                'description' => 'Cosmotown → My Account → Reseller API. Paste the key only, never your password.',
                'required' => true,
            ],
            [
                'name' => 'sandbox',
                'type' => 'checkbox',
                'label' => 'Sandbox mode',
                'description' => 'Send everything to Cosmotown\'s sandbox instead of registering real domains. Use a sandbox key when this is on.',
                'required' => false,
            ],
        ];
    }

    public function testConfig(): bool|string
    {
        try {
            $this->api()->testConnection();
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return true;
    }

    public function getProductConfig($values = []): array
    {
        return [
            [
                'name' => 'mode',
                'type' => 'select',
                'label' => 'Order type',
                'description' => 'Registration takes a new domain. Transfer moves one in from another registrar and asks the customer for an auth code.',
                'options' => [
                    ['value' => 'register', 'label' => 'Registration'],
                    ['value' => 'transfer', 'label' => 'Transfer in'],
                ],
                'default' => 'register',
                'required' => true,
            ],
            [
                'name' => 'years',
                'type' => 'number',
                'label' => 'Registration period (years)',
                'description' => 'How many years each order registers for. Renewals extend by the same amount.',
                'default' => 1,
                'required' => true,
            ],
            [
                'name' => 'nameservers',
                'type' => 'text',
                'label' => 'Default nameservers',
                'description' => 'Comma separated, applied at registration. Leave blank to keep Cosmotown\'s own nameservers.',
                'placeholder' => 'ns1.hostorio.com, ns2.hostorio.com',
                'required' => false,
            ],
        ];
    }

    public function getCheckoutConfig($product = null, $values = [], $settings = []): array
    {
        $fields = [
            [
                'name' => self::DOMAIN_KEY,
                'type' => 'text',
                'label' => 'Domain name',
                'placeholder' => 'example.com',
                'validation' => [new DomainRule, 'required'],
                'required' => true,
            ],
        ];

        if (($settings['mode'] ?? 'register') === 'transfer') {
            $fields[] = [
                'name' => self::AUTH_CODE_KEY,
                'type' => 'text',
                'label' => 'Authorisation code',
                'description' => 'Also called an EPP or transfer code. Get it from your current registrar, and make sure the domain is unlocked there.',
                'placeholder' => 'ABC123-xyz',
                'validation' => 'required|string|max:255',
                'required' => true,
            ];
        }

        return $fields;
    }

    public function createServer(Service $service, $settings, $properties)
    {
        $domain = $this->domain($properties);
        $years = max(1, (int) ($settings['years'] ?? 1));

        // Name the service after the domain before anything can fail. Without
        // this every domain shows as ".com #5" — Service::label falls back to
        // the product name and id — which is unusable once a customer holds
        // more than one, and useless to you in the admin list.
        if (!$service->getRawOriginal('label')) {
            $service->label = $domain;
            $service->save();
        }

        if (($settings['mode'] ?? 'register') === 'transfer') {
            return $this->startTransfer($service, $domain, $properties);
        }

        $this->api()->registerDomains([$domain => $years]);

        $service->properties()->updateOrCreate(
            ['key' => self::REGISTERED_KEY],
            ['name' => 'Registered at', 'value' => now()->toDateTimeString()],
        );

        // Nameservers are a separate call, and a failure here must not read as
        // a failed registration — the domain exists and is billable either way.
        if ($nameservers = $this->configuredNameservers($settings)) {
            try {
                $this->api()->saveNameservers($domain, $nameservers);
            } catch (Exception $e) {
                Log::warning("Cosmotown: registered {$domain} but could not set nameservers: " . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Domains have no concept of suspension. Locking is the closest useful
     * action: it leaves the site working but stops a transfer out while the
     * customer is in arrears.
     */
    public function suspendServer(Service $service, $settings, $properties)
    {
        $this->api()->setDomainOption($this->domain($properties), 'lock_domain', true);

        return true;
    }

    public function unsuspendServer(Service $service, $settings, $properties)
    {
        $this->api()->setDomainOption($this->domain($properties), 'lock_domain', false);

        return true;
    }

    /**
     * Deliberately does not delete anything at Cosmotown.
     *
     * There is no delete endpoint, and deleting a paid-up domain over an
     * unpaid invoice would destroy an asset the customer owns. The domain
     * lapses on its own expiry date instead; this only detaches it locally.
     */
    public function terminateServer(Service $service, $settings, $properties)
    {
        $service->properties()->where('key', self::REGISTERED_KEY)->delete();

        return true;
    }

    public function upgradeServer(Service $service, $settings, $properties)
    {
        throw new Exception('Domains cannot be upgraded. Register the new domain as a separate order.');
    }

    public function getActions(Service $service, $settings, $properties): array
    {
        // A transfer that has not landed yet is not ours to manage, and asking
        // Cosmotown about it would just error. Report progress instead.
        $transfer = $properties[self::TRANSFER_KEY] ?? null;

        if ($transfer === 'pending' || $transfer === 'failed') {
            return [[
                'type' => 'text',
                'label' => 'Transfer',
                'text' => $transfer === 'pending'
                    ? 'In progress. Transfers usually take 5 to 7 days, and your current registrar may email you to approve it.'
                    : 'Could not be completed. We have been notified and will be in touch.',
            ]];
        }

        $domain = $this->domain($properties);

        try {
            $info = $this->getDomainDetails($service, $settings, $properties);
        } catch (Exception $e) {
            return [[
                'type' => 'text',
                'label' => 'Domain',
                'text' => $domain . ' — could not reach Cosmotown',
            ]];
        }

        if (!$info) {
            return [[
                'type' => 'text',
                'label' => 'Domain',
                'text' => $domain . ' — not registered at Cosmotown yet',
            ]];
        }

        $locked = (bool) ($info['locked'] ?? false);
        $private = (bool) ($info['whois_privacy'] ?? false);

        return [
            [
                'type' => 'text',
                'label' => 'Domain',
                'text' => $domain,
            ],
            [
                'type' => 'text',
                'label' => 'Expires',
                'text' => $info['expiration_date'] ?? 'unknown',
            ],
            [
                'type' => 'text',
                'label' => 'Nameservers',
                'text' => implode(', ', $info['nameservers'] ?? []) ?: 'Cosmotown default',
            ],
            [
                'type' => 'text',
                'label' => 'Registrar lock',
                'text' => $locked ? 'Locked' : 'Unlocked',
            ],
            [
                'type' => 'text',
                'label' => 'WHOIS privacy',
                'text' => $private ? 'On' : 'Off',
            ],
            [
                'type' => 'button',
                'label' => $locked ? 'Unlock domain' : 'Lock domain',
                'function' => 'toggleLock',
            ],
            [
                'type' => 'button',
                'label' => $private ? 'Disable WHOIS privacy' : 'Enable WHOIS privacy',
                'function' => 'togglePrivacy',
            ],
        ];
    }

    /**
     * Action methods return the service URL so Livewire redirects back to the
     * page, which re-runs getActions() and shows the new state immediately.
     */
    public function toggleLock(Service $service, $settings, $properties): string
    {
        $domain = $this->domain($properties);
        $info = $this->api()->domainInfo($domain);

        $this->api()->setDomainOption($domain, 'lock_domain', !($info['locked'] ?? false));
        Cache::forget($this->infoCacheKey($domain));

        return route('services.show', $service);
    }

    public function togglePrivacy(Service $service, $settings, $properties): string
    {
        $domain = $this->domain($properties);
        $info = $this->api()->domainInfo($domain);

        $this->api()->setDomainOption($domain, 'enable_private_whois', !($info['whois_privacy'] ?? false));
        Cache::forget($this->infoCacheKey($domain));

        return route('services.show', $service);
    }

    /**
     * Transfers complete at the registry days later, so this only starts one.
     * checkTransfer() below finishes the job when Cosmotown reports COMPLETE.
     */
    private function startTransfer(Service $service, string $domain, array $properties): bool
    {
        $authCode = $properties[self::AUTH_CODE_KEY] ?? null;

        if (!$authCode) {
            throw new Exception('No authorisation code was supplied for this transfer.');
        }

        $this->api()->transferDomains([$domain => $authCode]);

        $service->properties()->updateOrCreate(
            ['key' => self::TRANSFER_KEY],
            ['name' => 'Transfer status', 'value' => 'pending'],
        );

        // The auth code is single use and worthless once the transfer starts,
        // so do not keep it sitting in the database.
        $service->properties()->where('key', self::AUTH_CODE_KEY)->delete();

        return true;
    }

    /**
     * Poll one in-flight transfer. Scheduled hourly, and safe to call again.
     */
    public function checkTransfer(Service $service, $settings, $properties): void
    {
        if (($properties[self::TRANSFER_KEY] ?? null) !== 'pending') {
            return;
        }

        $domain = $this->domain($properties);

        try {
            $statuses = $this->api()->domainStatus([$domain]);
        } catch (Exception $e) {
            // A registrar that is briefly unreachable is not a failed
            // transfer; leave it pending and try again next hour.
            Log::warning("Cosmotown: could not check transfer for {$domain}: " . $e->getMessage());

            return;
        }

        $result = $statuses[$domain] ?? null;

        if (!$result) {
            return;
        }

        if (strtoupper($result['status']) === 'COMPLETE') {
            $this->completeTransfer($service, $domain, $settings);

            return;
        }

        // Cosmotown only sends a message when something is wrong; progress
        // updates come back with an empty one.
        if (!empty($result['message'])) {
            $this->failTransfer($service, $domain, (string) $result['message']);
        }
    }

    private function completeTransfer(Service $service, string $domain, $settings): void
    {
        $service->properties()->updateOrCreate(
            ['key' => self::TRANSFER_KEY],
            ['name' => 'Transfer status', 'value' => 'complete'],
        );

        // Registered marks the domain as ours to manage, which unlocks the
        // nameserver editor and the lock and privacy controls.
        $service->properties()->updateOrCreate(
            ['key' => self::REGISTERED_KEY],
            ['name' => 'Registered at', 'value' => now()->toDateTimeString()],
        );

        if ($nameservers = $this->configuredNameservers($settings)) {
            try {
                $this->api()->saveNameservers($domain, $nameservers);
            } catch (Exception $e) {
                Log::warning("Cosmotown: transferred {$domain} but could not set nameservers: " . $e->getMessage());
            }
        }

        Cache::forget($this->infoCacheKey($domain));

        NotificationHelper::sendSystemEmailNotification(
            "Domain transfer completed: {$domain}",
            "The inbound transfer of {$domain} (service #{$service->id}) has completed.",
        );
    }

    private function failTransfer(Service $service, string $domain, string $reason): void
    {
        $service->properties()->updateOrCreate(
            ['key' => self::TRANSFER_KEY],
            ['name' => 'Transfer status', 'value' => 'failed'],
        );

        $message = "The inbound transfer of {$domain} (service #{$service->id}) failed.\n\n"
            . "Reason: {$reason}\n\n"
            . 'The customer has paid and holds nothing. Usually the domain is locked at the losing '
            . 'registrar, the auth code is wrong, or it was registered less than 60 days ago. '
            . 'Contact them, then restart the transfer once it is resolved.';

        Log::error($message);

        NotificationHelper::sendSystemEmailNotification(
            "Domain transfer FAILED: {$domain}",
            $message,
        );
    }

    /**
     * Current registrar state for the client area domain pages.
     *
     * Reached through ExtensionHelper::callService so the credentials belong
     * to the server row this service actually uses.
     */
    public function getDomainDetails(Service $service, $settings, $properties): ?array
    {
        // A transfer still in flight is not ours to manage yet.
        if (($properties[self::TRANSFER_KEY] ?? null) === 'pending') {
            return null;
        }

        $info = $this->cachedDomainInfo($this->domain($properties));

        // Cosmotown is the authority on whether a domain is manageable, not a
        // flag this extension happened to write. Domains that predate the
        // panel, or were registered straight at the registrar, are just as
        // manageable — so adopt them rather than refusing to touch them.
        if ($info && !isset($properties[self::REGISTERED_KEY])) {
            $service->properties()->updateOrCreate(
                ['key' => self::REGISTERED_KEY],
                ['name' => 'Registered at', 'value' => now()->toDateTimeString()],
            );
        }

        return $info;
    }

    /**
     * @param  array<string>  $nameservers
     */
    public function updateNameservers(Service $service, $settings, $properties, array $nameservers = []): bool
    {
        $domain = $this->domain($properties);

        $this->api()->saveNameservers($domain, $nameservers);
        Cache::forget($this->infoCacheKey($domain));

        return true;
    }

    public function setLock(Service $service, $settings, $properties, bool $locked = true): bool
    {
        $domain = $this->domain($properties);

        $this->api()->setDomainOption($domain, 'lock_domain', $locked);
        Cache::forget($this->infoCacheKey($domain));

        return true;
    }

    public function setPrivacy(Service $service, $settings, $properties, bool $enabled = true): bool
    {
        $domain = $this->domain($properties);

        $this->api()->setDomainOption($domain, 'enable_private_whois', $enabled);
        Cache::forget($this->infoCacheKey($domain));

        return true;
    }

    /**
     * getActions() runs on every view of the service page, so an uncached
     * lookup would spend API quota on page refreshes. Short TTL keeps the
     * panel honest; the toggles above clear it so changes show immediately.
     */
    private function cachedDomainInfo(string $domain): ?array
    {
        return Cache::remember(
            $this->infoCacheKey($domain),
            now()->addMinutes(5),
            fn () => $this->api()->domainInfo($domain),
        );
    }

    private function infoCacheKey(string $domain): string
    {
        return 'cosmotown.domaininfo.' . md5($domain);
    }

    /**
     * Called when an invoice referencing this service is paid.
     *
     * Renewing costs real money, so this is deliberately conservative: it
     * refuses anything it cannot positively identify as a renewal.
     */
    public function renewDomain(Service $service, $settings, $properties, $invoiceId = null): void
    {
        // No registration on record means this is the first invoice; the order
        // flow calls createServer() right after this, so renewing here would
        // buy a second year the customer has not paid for.
        if (!isset($properties[self::REGISTERED_KEY]) || !isset($properties[self::DOMAIN_KEY])) {
            return;
        }

        // Guard against the event being delivered twice for one invoice, which
        // would silently buy a second year at your cost.
        if ($invoiceId && ($properties[self::LAST_RENEWAL_KEY] ?? null) == $invoiceId) {
            return;
        }

        $domain = $this->domain($properties);
        $years = max(1, (int) ($settings['years'] ?? 1));

        try {
            $this->api()->renewDomains([$domain => $years]);

            if ($invoiceId) {
                $service->properties()->updateOrCreate(
                    ['key' => self::LAST_RENEWAL_KEY],
                    ['name' => 'Last renewal invoice', 'value' => (string) $invoiceId],
                );
            }
        } catch (Exception $e) {
            // Paymenter has already pushed expires_at forward, so a silent
            // failure here shows the domain as renewed while it quietly
            // expires at the registrar months later. Make it loud.
            $message = "Cosmotown renewal FAILED for {$domain} (service #{$service->id}). "
                . "Paymenter has marked it renewed, but the registrar has not. "
                . "Renew it manually in Cosmotown.\n\nError: " . $e->getMessage();

            Log::error($message);

            NotificationHelper::sendSystemEmailNotification(
                "Domain renewal failed: {$domain}",
                $message,
            );
        }
    }

    private function ownsService(Service $service): bool
    {
        return $service->product?->server?->extension === 'Cosmotown';
    }

    private function domain(array $properties): string
    {
        $domain = $properties[self::DOMAIN_KEY] ?? null;

        if (!$domain) {
            throw new Exception('This service has no domain recorded, so it was never registered.');
        }

        return strtolower(trim($domain));
    }

    private function configuredNameservers($settings): array
    {
        $raw = $settings['nameservers'] ?? '';

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function api(): CosmotownApi
    {
        return new CosmotownApi(
            (string) $this->config('apikey'),
            (bool) $this->config('sandbox'),
        );
    }
}
