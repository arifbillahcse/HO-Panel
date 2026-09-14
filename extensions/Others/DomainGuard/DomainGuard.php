<?php

namespace Paymenter\Extensions\Others\DomainGuard;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Events\Property\Created;
use App\Events\Property\Updated;
use App\Exceptions\DisplayException;
use App\Models\Property;
use App\Models\Service;
use Illuminate\Support\Facades\Event;

/**
 * Stops the same domain being sold twice.
 *
 * Paymenter lets any product carry a domain — a hosting plan through a config
 * option, a registration through the Cosmotown checkout field — but never
 * checks that a domain is free before creating the service. Two customers can
 * therefore end up holding one domain, and provisioning can collide on the
 * server.
 *
 * A domain is always written as a Property with the key "domain" on a Service.
 * That single fact lets one listener cover every product type: whatever route
 * the order took, the domain lands here. The write happens inside the cart's
 * database transaction (Cart::checkout wraps the whole thing in
 * DB::beginTransaction), so throwing a DisplayException rolls the entire order
 * back — service, invoice and all — and the customer sees the reason instead
 * of a half-made duplicate order. It is the WHMCS "domain already active"
 * block, applied one layer deeper where nothing can slip past it.
 *
 * This is a safety net, not the only gate: the Cosmotown extension also runs a
 * UniqueDomain validation rule so a registration is refused inline, on the
 * order form, before checkout. This catches the hosting case that rule cannot
 * reach, and the race where two orders pass validation at the same moment.
 */
#[ExtensionMeta(
    name: 'Domain Guard',
    description: 'Prevents the same domain being ordered on more than one active service.',
    version: '1.0.0',
    author: 'Hostorio',
)]
class DomainGuard extends Extension
{
    /**
     * The property key a domain is stored under, for both hosting config
     * options (env variable "domain") and the Cosmotown checkout field.
     */
    public const DOMAIN_KEY = 'domain';

    public function boot()
    {
        // Both events, because the value can arrive as a fresh row or as an
        // update to an existing one (a customer editing a domain, a re-order).
        Event::listen(Created::class, fn (Created $event) => $this->guard($event->property));
        Event::listen(Updated::class, fn (Updated $event) => $this->guard($event->property));
    }

    /**
     * Refuse a domain that is already live on another service.
     */
    private function guard(Property $property): void
    {
        // Cheap early exits keep this off the hot path for every other
        // property write in the application (user addresses, config values,
        // the extension's own bookkeeping keys).
        if ($property->key !== self::DOMAIN_KEY || $property->model_type !== Service::class) {
            return;
        }

        $domain = strtolower(trim((string) $property->value));

        if ($domain === '') {
            return;
        }

        // Exclude the service this property belongs to, so a legitimate write
        // to a domain's own service never trips over itself.
        if (self::isTaken($domain, (int) $property->model_id)) {
            throw new DisplayException(
                "The domain {$domain} is already active on another service. "
                . 'Each domain can only be used once — please choose a different one.'
            );
        }
    }

    /**
     * Is this domain already held by a service that has not been cancelled?
     *
     * Static and self-contained so the Cosmotown validation rule can reuse it
     * without the two extensions depending on each other.
     *
     * @param  int|null  $exceptServiceId  A service to ignore, normally the one being written.
     */
    public static function isTaken(string $domain, ?int $exceptServiceId = null): bool
    {
        $domain = strtolower(trim($domain));

        if ($domain === '') {
            return false;
        }

        return Service::query()
            ->where('status', '!=', Service::STATUS_CANCELLED)
            ->when($exceptServiceId, fn ($query) => $query->where('id', '!=', $exceptServiceId))
            ->whereHas('properties', fn ($query) => $query
                ->where('key', self::DOMAIN_KEY)
                ->whereRaw('LOWER(value) = ?', [$domain]))
            ->exists();
    }
}
