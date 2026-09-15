<?php

namespace Paymenter\Extensions\Others\DomainService\Models;

use App\Classes\Price;
use App\Models\Cart;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Paymenter\Extensions\Others\DomainService\Services\DomainOrderService;
use Throwable;

/**
 * A domain sitting in the shared cart alongside hosting products, priced
 * live off the current TLD pricing table rather than a snapshot — the same
 * approach CartItem uses for products, so a price change before checkout is
 * never stale.
 */
class DomainCartItem extends Model
{
    protected $fillable = ['cart_id', 'name', 'tld', 'action', 'years', 'auth_code'];

    protected $casts = [
        'years' => 'integer',
        'auth_code' => 'encrypted',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function price(): Attribute
    {
        return Attribute::make(
            get: function () {
                $currencyCode = $this->cart?->currency_code ?? session('currency', config('settings.default_currency'));
                $currency = Currency::find($currencyCode);

                try {
                    $resolved = app(DomainOrderService::class)->resolveForCart($this->name, $currencyCode);
                } catch (Throwable $e) {
                    return new Price(['price' => null, 'setup_fee' => null, 'currency' => null]);
                }

                $unit = $this->action === 'transfer'
                    ? (float) $resolved['pricing']->transfer_price
                    : (float) $resolved['pricing']->register_price;

                $total = $this->action === 'transfer' ? $unit : $unit * $this->years;

                return new Price(['price' => $total, 'currency' => $currency, 'setup_fee' => 0], apply_exclusive_tax: true);
            }
        );
    }
}
