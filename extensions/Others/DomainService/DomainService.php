<?php

namespace Paymenter\Extensions\Others\DomainService;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;

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
        // Leaves no orphan tables. Domains and pricing go with it, so only
        // uninstall once nothing depends on this module.
        ExtensionHelper::rollbackMigrations(self::MIGRATIONS);
    }

    public function boot()
    {
        // Routes, navigation, the renewal schedule and the Invoice\Paid
        // listener are registered in the next Phase 1 steps. This foundation
        // push is schema, models and the driver contract only.
    }
}
