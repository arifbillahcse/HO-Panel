<?php

namespace Paymenter\Extensions\Others\DomainService;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Events\Invoice\Paid;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Paymenter\Extensions\Others\DomainService\Livewire\Domains;
use Paymenter\Extensions\Others\DomainService\Livewire\Search;
use Paymenter\Extensions\Others\DomainService\Livewire\Transfer;
use Paymenter\Extensions\Others\DomainService\Jobs\ProvisionDomainJob;
use Paymenter\Extensions\Others\DomainService\Models\DomainInvoice;
use Paymenter\Extensions\Others\DomainService\Services\DomainRenewalService;
use Paymenter\Extensions\Others\DomainService\Services\TransferPollService;
use Illuminate\Support\Facades\Gate;
use Paymenter\Extensions\Others\DomainService\Models\Domain;
use Paymenter\Extensions\Others\DomainService\Models\DomainRegistrar;
use Paymenter\Extensions\Others\DomainService\Models\DomainTld;
use Paymenter\Extensions\Others\DomainService\Policies\DomainPolicy;
use Paymenter\Extensions\Others\DomainService\Policies\DomainRegistrarPolicy;
use Paymenter\Extensions\Others\DomainService\Policies\DomainTldPolicy;

#[ExtensionMeta(
    name: 'Domain Service',
    description: 'Registrar-agnostic domain registration, renewal and transfer with central TLD pricing.',
    version: '0.1.0',
    author: 'Hostorio',
)]
class DomainService extends Extension
{
    private const MIGRATIONS = 'extensions/Others/DomainService/database/migrations';

    public function installed()
    {
        ExtensionHelper::runMigrations(self::MIGRATIONS);
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations(self::MIGRATIONS);
    }

    public function boot()
    {
        // Customer-facing routes, views and components.
        require __DIR__ . '/routes/web.php';
        View::addNamespace('domainservice', __DIR__ . '/resources/views');
        Livewire::component('domainservice.search', Search::class);
        Livewire::component('domainservice.transfer', Transfer::class);
        Livewire::component('domainservice.domains.index', Domains\Index::class);
        Livewire::component('domainservice.domains.show', Domains\Show::class);

        // Domains in the storefront nav beside Shop.
        Event::listen('navigation', fn () => [
            'name' => 'Domains',
            'url' => route('domainservice.search'),
            'icon' => 'ri-global',
            'priority' => 20,
        ]);

        // And in the client sidebar.
        Event::listen('navigation.dashboard', fn () => [
            'name' => 'Domains',
            'url' => route('domainservice.domains'),
            'icon' => 'ri-global',
            'condition' => Auth::check(),
            'priority' => 25,
        ]);

        // Admin authorization. The Filament resources under Admin/Resources are
        // discovered by the panel automatically; these policies gate them.
        Gate::policy(DomainRegistrar::class, DomainRegistrarPolicy::class);
        Gate::policy(DomainTld::class, DomainTldPolicy::class);
        Gate::policy(Domain::class, DomainPolicy::class);

        Event::listen('permissions', fn () => [
            'admin.domains.view' => 'View domains, registrars and pricing',
            'admin.domains.manage' => 'Manage domains, registrars and pricing',
        ]);

        // When a domain invoice is paid, hand the registrar work to a queued
        // job. Paymenter's existing payment flow (EPS included) fires this;
        // there is nothing extra to wire on the payment side.
        Event::listen(Paid::class, function (Paid $event) {
            DomainInvoice::where('invoice_id', $event->invoice->id)
                ->where('processed', false)
                ->get()
                ->each(fn (DomainInvoice $link) => ProvisionDomainJob::dispatch($link->id));
        });

        // Raise renewal invoices ahead of expiry. Console-only: boot() also
        // runs on web requests, where scheduling is pure overhead.
        if (app()->runningInConsole()) {
            Schedule::call(fn () => (new DomainRenewalService)->sweep())
                ->name('domainservice-renewals')
                ->daily()
                ->withoutOverlapping();

            // Inbound transfers complete at the registry with no callback, so poll.
            Schedule::call(fn () => (new TransferPollService)->sweep())
                ->name('domainservice-transfers')
                ->hourly()
                ->withoutOverlapping();
        }

        // The customer routes, nav and pages arrive in step 6.
    }
}
