<?php

namespace Paymenter\Extensions\Others\DomainService\Registrars;

use Exception;
use Illuminate\Support\Str;
use Paymenter\Extensions\Others\DomainService\Contracts\RegistrarDriver;
use Paymenter\Extensions\Others\DomainService\Models\DomainRegistrar;

/**
 * Turns a stored registrar row into a live driver instance.
 *
 * The driver key ("cosmotown") maps by convention to a class in the Drivers
 * namespace ("CosmotownDriver"), constructed with that registrar's own
 * credentials and sandbox flag. Adding a registrar is therefore only: drop a
 * Driver class in, add a row in the admin — no wiring here to change.
 */
class RegistrarManager
{
    public function make(DomainRegistrar $registrar): RegistrarDriver
    {
        $class = 'Paymenter\\Extensions\\Others\\DomainService\\Drivers\\' . Str::studly($registrar->driver) . 'Driver';

        if (!class_exists($class)) {
            throw new Exception("No domain registrar driver for '{$registrar->driver}'.");
        }

        $driver = new $class((array) $registrar->credentials, (bool) $registrar->sandbox);

        if (!$driver instanceof RegistrarDriver) {
            throw new Exception("Driver '{$registrar->driver}' does not implement RegistrarDriver.");
        }

        return $driver;
    }

    /**
     * Driver keys that ship with the module, for the admin dropdown.
     *
     * @return array<string, string>  key => label
     */
    public function available(): array
    {
        return [
            'cosmotown' => 'Cosmotown',
            // 'resellerclub' => 'ResellerClub',  // Phase 3
        ];
    }
}
