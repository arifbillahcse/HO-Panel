<?php

namespace App\Models;

use App\Classes\Price;
use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;

#[ObservedBy([OrderObserver::class])]
class Order extends Model implements Auditable
{
    use HasFactory, Traits\Auditable;

    protected $fillable = ['user_id', 'currency_code'];

    public bool $send_create_email = true;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Domains bought through the cart alongside hosting. Belongs to the
     * Domain Service extension (extensions/Others/DomainService) — this
     * relation assumes it is installed, same as the invoices() attribute
     * below.
     */
    public function domains()
    {
        return $this->hasMany(\Paymenter\Extensions\Others\DomainService\Models\Domain::class);
    }

    /**
     * Get the currency corresponding to the service.
     */
    public function currency()
    {
        return $this->hasOne(Currency::class, 'code', 'currency_code');
    }

    /**
     * Total of the order.
     *
     * @return float
     */
    public function total(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->services->sum(fn ($service) => $service->price * $service->quantity)
        );
    }

    /**
     * Total of the order.
     *
     * @return string
     */
    public function formattedTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => new Price(['price' => $this->total, 'currency' => $this->currency])
        );
    }

    /**
     * Get all invoices for the order.
     */
    public function invoices(): Attribute
    {
        // Each service has invoices (it is a hasManyThrough relationship order -> service -> invoiceItem -> invoice)
        $invoicesId = $this->services->map(fn ($service) => $service->invoiceItems->map(fn ($invoiceItem) => $invoiceItem->invoice_id))->flatten()
            // A domain bought alongside hosting shares that same invoice via its own DomainInvoice link.
            ->merge($this->domains->map(fn ($domain) => $domain->invoices->pluck('invoice_id'))->flatten());

        return new Attribute(
            get: fn () => Invoice::whereIn('id', $invoicesId)
        );
    }
}
