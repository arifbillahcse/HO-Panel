<?php

namespace Paymenter\Extensions\Servers\Cosmotown\Rules;

use App\Models\Service;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuse a domain that is already live on another service, at order time.
 *
 * Attached to the Cosmotown checkout field so a customer trying to register a
 * domain someone already holds sees the error inline on the order form, before
 * anything is added to the cart — the WHMCS experience.
 *
 * The DomainGuard extension enforces the same rule one layer deeper, at the
 * moment the domain property is written, which also covers hosting products
 * and the race where two orders validate at the same instant. This rule is the
 * friendly front door; that guard is the lock.
 *
 * Self-contained rather than calling DomainGuard so registrations keep working
 * even if that extension is disabled.
 */
class UniqueDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = strtolower(trim((string) $value));

        if ($domain === '') {
            return;
        }

        $taken = Service::query()
            ->where('status', '!=', Service::STATUS_CANCELLED)
            ->whereHas('properties', fn ($query) => $query
                ->where('key', 'domain')
                ->whereRaw('LOWER(value) = ?', [$domain]))
            ->exists();

        if ($taken) {
            $fail("The domain {$domain} is already registered with us. Please choose a different name.");
        }
    }
}
