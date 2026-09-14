<?php

namespace Paymenter\Extensions\Others\DomainService;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\Event;
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
        // Admin authorization. The Filament resources under Admin/Resources are
        // discovered by the panel automatically; these policies gate them.
        Gate::policy(DomainRegistrar::class, DomainRegistrarPolicy::class);
        Gate::policy(DomainTld::class, DomainTldPolicy::class);
        Gate::policy(Domain::class, DomainPolicy::class);

        Event::listen('permissions', fn () => [
            'admin.domains.view' => 'View domains, registrars and pricing',
            'admin.domains.manage' => 'Manage domains, registrars and pricing',
        ]);

        // The customer routes/nav, the renewal schedule and the Invoice\Paid
        // listener arrive in the next Phase 1 steps.
    }
}
