<?php

namespace Paymenter\Extensions\Others\DomainService\Models;

use Illuminate\Database\Eloquent\Model;

class DomainPricing extends Model
{
    protected $table = 'domain_pricing';

    protected $fillable = [
        'tld_id', 'currency', 'register_price', 'renew_price', 'transfer_price',
    ];

    protected $casts = [
        'register_price' => 'decimal:2',
        'renew_price' => 'decimal:2',
        'transfer_price' => 'decimal:2',
    ];

    public function tld()
    {
        return $this->belongsTo(DomainTld::class, 'tld_id');
    }

    public function priceForAction(string $action): float
    {
        return (float) match ($action) {
            'transfer' => $this->transfer_price,
            'renew' => $this->renew_price,
            default => $this->register_price,
        };
    }
}
