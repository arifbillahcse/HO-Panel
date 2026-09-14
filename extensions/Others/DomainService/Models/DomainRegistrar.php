<?php

namespace Paymenter\Extensions\Others\DomainService\Models;

use Illuminate\Database\Eloquent\Model;
use Paymenter\Extensions\Others\DomainService\Contracts\RegistrarDriver;
use Paymenter\Extensions\Others\DomainService\Registrars\RegistrarManager;

class DomainRegistrar extends Model
{
    protected $fillable = ['driver', 'name', 'credentials', 'sandbox', 'enabled'];

    protected $casts = [
        // Encrypted at rest: a database dump never exposes an API key.
        'credentials' => 'encrypted:array',
        'sandbox' => 'boolean',
        'enabled' => 'boolean',
    ];

    public function tlds()
    {
        return $this->hasMany(DomainTld::class, 'registrar_id');
    }

    public function domains()
    {
        return $this->hasMany(Domain::class, 'registrar_id');
    }

    /** The live driver instance for this registrar, credentials injected. */
    public function driver(): RegistrarDriver
    {
        return app(RegistrarManager::class)->make($this);
    }
}
