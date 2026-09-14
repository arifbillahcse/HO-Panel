<?php

namespace Paymenter\Extensions\Others\DomainService\Models;

use Illuminate\Database\Eloquent\Model;

class DomainTld extends Model
{
    protected $fillable = [
        'tld', 'registrar_id', 'min_years', 'max_years',
        'grace_days', 'redemption_days', 'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'min_years' => 'integer',
        'max_years' => 'integer',
    ];

    public function registrar()
    {
        return $this->belongsTo(DomainRegistrar::class, 'registrar_id');
    }

    public function pricing()
    {
        return $this->hasMany(DomainPricing::class, 'tld_id');
    }

    public function priceFor(string $currency): ?DomainPricing
    {
        return $this->pricing->firstWhere('currency', strtoupper($currency));
    }
}
