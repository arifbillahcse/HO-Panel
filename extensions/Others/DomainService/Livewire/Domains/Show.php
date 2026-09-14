<?php

namespace Paymenter\Extensions\Others\DomainService\Livewire\Domains;

use App\Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Paymenter\Extensions\Others\DomainService\Models\Domain;
use Throwable;

class Show extends Component
{
    #[Locked]
    public Domain $domain;

    public array $nameservers = ['', '', '', '', ''];

    public bool $locked = false;

    public bool $privacy = false;

    public bool $autorenew = true;

    public bool $manageable = false;

    public bool $unreachable = false;

    /** @var array<string, bool> */
    public array $capabilities = [];

    /** Revealed only on request, never persisted, never logged. */
    public ?string $eppCode = null;

    public array $dnsRecords = [];

    public string $newDnsType = 'A';

    public string $newDnsHost = '';

    public string $newDnsValue = '';

    public int $newDnsTtl = 3600;

    public function mount(Domain $domain): void
    {
        // Ownership: a customer may only open their own domain, whatever id
        // they put in the URL.
        abort_unless($domain->user_id === Auth::id(), 403);

        $this->domain = $domain;
        $this->autorenew = $domain->autorenew;
        $this->load();
    }

    private function load(): void
    {
        // A domain still being registered or transferred is not manageable yet.
        if (in_array($this->domain->status, [Domain::STATUS_PENDING, Domain::STATUS_TRANSFER_PENDING, Domain::STATUS_TRANSFER_FAILED], true)) {
            return;
        }

        $this->capabilities = $this->domain->driver()->capabilities();

        try {
            $info = $this->domain->driver()->getInfo($this->domain);
        } catch (Throwable $e) {
            $this->unreachable = true;

            return;
        }

        if (!$info) {
            return;
        }

        $this->manageable = true;
        $this->locked = (bool) ($info['locked'] ?? false);
        $this->privacy = (bool) ($info['privacy'] ?? false);

        $existing = array_values($info['nameservers'] ?? []);
        $this->nameservers = array_pad(array_slice($existing, 0, 5), 5, '');

        if ($this->capabilities['dnsRecords'] ?? false) {
            $this->loadDnsRecords();
        }
    }

    private function loadDnsRecords(): void
    {
        try {
            $this->dnsRecords = $this->domain->driver()->getDnsRecords($this->domain);
        } catch (Throwable $e) {
            // Not fatal to the page — nameservers and protection still work.
            $this->dnsRecords = [];
        }
    }

    public function rules(): array
    {
        return [
            'nameservers' => 'array',
            'nameservers.*' => ['nullable', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i'],
        ];
    }

    public function saveNameservers(): void
    {
        $this->validate();

        $values = array_values(array_filter(array_map('trim', $this->nameservers)));

        if (count($values) < 2) {
            $this->addError('nameservers.0', 'Enter at least two nameservers.');

            return;
        }

        $this->run(fn () => $this->domain->driver()->saveNameservers($this->domain, $values),
            'Nameservers updated. Changes can take a few hours to spread across the internet.');
    }

    /**
     * Sets the new value locally rather than reloading from the registrar:
     * Cosmotown (and registrars generally) can apply a lock/privacy change
     * with a short propagation delay, so an immediate read-back after the
     * write routinely still reports the old value and would make a
     * successful toggle look like it silently failed.
     */
    public function toggleLock(): void
    {
        $newValue = !$this->locked;

        $this->run(function () use ($newValue) {
            $this->domain->driver()->setLock($this->domain, $newValue);
            $this->locked = $newValue;
            $this->domain->update(['locked' => $newValue]);
        }, ($this->locked ? 'Domain unlocked.' : 'Domain locked.') . ' It may take a minute to show as updated everywhere.', reload: false);
    }

    public function togglePrivacy(): void
    {
        $newValue = !$this->privacy;

        $this->run(function () use ($newValue) {
            $this->domain->driver()->setPrivacy($this->domain, $newValue);
            $this->privacy = $newValue;
            $this->domain->update(['privacy' => $newValue]);
        }, ($this->privacy ? 'WHOIS privacy disabled.' : 'WHOIS privacy enabled.') . ' It may take a minute to show as updated everywhere.', reload: false);
    }

    /**
     * Autorenew is tracked here, not at the registrar — both shipped drivers
     * report autoRenew=false in capabilities() because the module raises the
     * renewal invoice itself. Turning this off just stops that invoice from
     * being raised; it never touches the registrar.
     */
    public function toggleAutorenew(): void
    {
        $this->autorenew = !$this->autorenew;
        $this->domain->update(['autorenew' => $this->autorenew]);

        $this->notify(
            $this->autorenew
                ? 'Auto-renew turned on. We will invoice you ahead of the renewal date.'
                : 'Auto-renew turned off. Renew manually before it expires or the domain will lapse.',
            'success',
        );
    }

    /**
     * Reveals the transfer-out authorisation code, for registrars whose API
     * supports it. Rate-limited: this is effectively a security credential
     * for the domain, not routine account data.
     */
    public function getEppCode(): void
    {
        if (!($this->capabilities['eppRetrieval'] ?? false)) {
            return;
        }

        $key = 'domaineppcode:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->notify('Too many attempts. Please wait a minute and try again.', 'error');

            return;
        }
        RateLimiter::hit($key, 60);

        try {
            $code = $this->domain->driver()->getEppCode($this->domain);

            if (!$code) {
                $this->notify('The registrar did not return a code. Contact support.', 'error');

                return;
            }

            $this->eppCode = $code;
        } catch (Throwable $e) {
            $this->notify($e->getMessage(), 'error');
        }
    }

    public function hideEppCode(): void
    {
        $this->eppCode = null;
    }

    public function dnsRules(): array
    {
        return [
            'newDnsType' => ['required', 'in:A,AAAA,CNAME,MX,TXT,NS'],
            'newDnsHost' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9*._-]+$/i'],
            'newDnsValue' => ['required', 'string', 'max:1024'],
            'newDnsTtl' => ['required', 'integer', 'min:60', 'max:86400'],
        ];
    }

    public function addDnsRecordAction(): void
    {
        if (!($this->capabilities['dnsRecords'] ?? false)) {
            return;
        }

        $this->validate($this->dnsRules(), [], [
            'newDnsType' => 'record type',
            'newDnsHost' => 'host',
            'newDnsValue' => 'value',
            'newDnsTtl' => 'TTL',
        ]);

        $key = 'domaindns:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->notify('Too many changes. Please wait a minute and try again.', 'error');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->run(function () {
            $this->domain->driver()->addDnsRecord($this->domain, [
                'type' => $this->newDnsType,
                'host' => $this->newDnsHost,
                'value' => $this->newDnsValue,
                'ttl' => $this->newDnsTtl,
            ]);
            $this->loadDnsRecords();
            $this->reset('newDnsHost', 'newDnsValue');
        }, 'DNS record added.', reload: false);
    }

    public function deleteDnsRecordAction(int $index): void
    {
        if (!($this->capabilities['dnsRecords'] ?? false) || !isset($this->dnsRecords[$index])) {
            return;
        }

        $record = $this->dnsRecords[$index];

        $this->run(function () use ($record) {
            $this->domain->driver()->deleteDnsRecord($this->domain, $record);
            $this->loadDnsRecords();
        }, 'DNS record removed.', reload: false);
    }

    /**
     * @param  bool  $reload  Re-run load() afterwards — needed for anything
     *                        that can change "manageable" or capabilities
     *                        (nameservers, lock, privacy); skipped for DNS
     *                        record actions, which already refresh their own
     *                        list and would otherwise pay for a second full
     *                        registrar lookup.
     */
    private function run(callable $action, string $success, bool $reload = true): void
    {
        try {
            $action();
            $this->notify($success, 'success');

            if ($reload) {
                $this->load();
            }
        } catch (Throwable $e) {
            $this->notify($e->getMessage(), 'error');
        }
    }

    public function render()
    {
        return view('domainservice::domains.show')
            ->layoutData(['title' => 'Domain', 'sidebar' => true]);
    }
}
