<?php

namespace Paymenter\Extensions\Servers\Cosmotown\Livewire\Domains;

use App\Helpers\ExtensionHelper;
use App\Livewire\Component;
use App\Models\Service;
use Livewire\Attributes\Locked;
use Throwable;

class Show extends Component
{
    /** Route-bound and gated by can:view,service — never trust it from input. */
    #[Locked]
    public Service $service;

    /** Registrar state, or null when the domain is not registered yet. */
    #[Locked]
    public ?array $details = null;

    /** Five slots because that is what Cosmotown accepts. */
    public array $nameservers = ['', '', '', '', ''];

    public bool $locked = false;

    public bool $privacy = false;

    public bool $unreachable = false;

    public function mount(Service $service): void
    {
        $this->service = $service;
        $this->load();
    }

    private function load(): void
    {
        try {
            $this->details = ExtensionHelper::callService($this->service, 'getDomainDetails');
        } catch (Throwable $e) {
            $this->unreachable = true;

            return;
        }

        if (!$this->details) {
            return;
        }

        $this->locked = (bool) ($this->details['locked'] ?? false);
        $this->privacy = (bool) ($this->details['whois_privacy'] ?? false);

        $existing = array_values($this->details['nameservers'] ?? []);
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

        // Registries require at least two. Catching it here saves a round trip
        // and gives a clearer message than the registrar's error would.
        if (count($values) < 2) {
            $this->addError('nameservers.0', 'Enter at least two nameservers.');

            return;
        }

        try {
            ExtensionHelper::callService($this->service, 'updateNameservers', [$values]);
            $this->notify('Nameservers updated. Changes can take a few hours to spread across the internet.', 'success');
            $this->load();
        } catch (Throwable $e) {
            $this->notify($e->getMessage(), 'error');
        }
    }

    public function toggleLock(): void
    {
        try {
            ExtensionHelper::callService($this->service, 'setLock', [!$this->locked]);
            $this->notify($this->locked ? 'Domain unlocked.' : 'Domain locked.', 'success');
            $this->load();
        } catch (Throwable $e) {
            $this->notify($e->getMessage(), 'error');
        }
    }

    public function togglePrivacy(): void
    {
        try {
            ExtensionHelper::callService($this->service, 'setPrivacy', [!$this->privacy]);
            $this->notify($this->privacy ? 'WHOIS privacy disabled.' : 'WHOIS privacy enabled.', 'success');
            $this->load();
        } catch (Throwable $e) {
            $this->notify($e->getMessage(), 'error');
        }
    }

    public function render()
    {
        return view('cosmotown::domains.show')->layoutData([
            'title' => 'Domain',
            'sidebar' => true,
        ]);
    }
}
