<?php

namespace Paymenter\Extensions\Others\DomainService\Livewire\Domains;

use App\Livewire\Component;
use Illuminate\Support\Facades\Auth;
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

    public bool $manageable = false;

    public bool $unreachable = false;

    public function mount(Domain $domain): void
    {
        // Ownership: a customer may only open their own domain, whatever id
        // they put in the URL.
        abort_unless($domain->user_id === Auth::id(), 403);

        $this->domain = $domain;
        $this->load();
    }

    private function load(): void
    {
        // A domain still being registered or transferred is not manageable yet.
        if (in_array($this->domain->status, [Domain::STATUS_PENDING, Domain::STATUS_TRANSFER_PENDING, Domain::STATUS_TRANSFER_FAILED], true)) {
            return;
        }

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

    public function toggleLock(): void
    {
        $this->run(fn () => $this->domain->driver()->setLock($this->domain, !$this->locked),
            $this->locked ? 'Domain unlocked.' : 'Domain locked.');
    }

    public function togglePrivacy(): void
    {
        $this->run(fn () => $this->domain->driver()->setPrivacy($this->domain, !$this->privacy),
            $this->privacy ? 'WHOIS privacy disabled.' : 'WHOIS privacy enabled.');
    }

    private function run(callable $action, string $success): void
    {
        try {
            $action();
            $this->notify($success, 'success');
            $this->load();
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
