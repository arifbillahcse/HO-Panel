# Core patches on top of upstream Paymenter

This fork carries two small, deliberate changes to files that ship with
upstream Paymenter (`app/`), rather than living in `extensions/`. They exist
because there's no extension hook that can fix either bug from outside core.

Paymenter's own in-panel "Update" button (Admin → Updates) runs a script that
deletes and replaces the entire `app/` directory on every update — it would
silently wipe both of these. **Don't use that button on this fork.** Update by
merging upstream `master` into the working branch instead (`git fetch` +
`git merge`); a real merge will flag these two files as conflicts instead of
silently discarding the fix, so they never get lost by accident.

Both are genuine upstream bugs, not something specific to this fork's custom
extensions — worth reporting to https://github.com/paymenter/paymenter so
they can be fixed for everyone; once merged upstream, these patches can be
dropped entirely.

## 1. `app/Policies/BasePolicy.php`

**Bug:** `adminPermission()` gated on `request()->is('admin/*')`, but that
pattern requires a literal `admin/` followed by something — it never matches
the bare `/admin` path, which is exactly the dashboard's own URL (no
trailing segment). Every other admin page (`/admin/domains`, `/admin/orders`,
…) matched fine, so any resource whose policy extends `BasePolicy` — this
includes core `InvoicePolicy`, `TicketPolicy`, `InvoiceTransactionPolicy`,
`ServicePolicy`, and this fork's Domain Service policies — could lose its
sidebar nav group specifically when landing on the dashboard, with no
caching or reload fixing it.

**Fix:** also accept the bare `admin` path:

```php
return (request()->is('admin') || request()->is('admin/*') || request()->routeIs('paymenter.livewire.update')) && $user->hasPermission($permission);
```

**On a merge conflict here:** take upstream's version of the file, then
re-add the `request()->is('admin')` alternative to the `adminPermission()`
condition.

## 2. `app/Classes/Cart.php`

**Bug:** `Cart::get()` memoized the current cart with PHP's `once()` helper,
which caches by call site for the entire request. `validateCoupon()` reads
the cart (via `get()`) *before* a coupon is saved when the coupon is
product-restricted — so a later read in the same request (Livewire's
`updateTotal()`, called right after `applyCoupon()` returns) kept getting
that pre-coupon snapshot back instead of a fresh one, even though the
database was already correct. The coupon discount, and any other same-request
cart change, only showed up after a full page reload.

**Fix:** replaced the `once()` memoization with an explicit, resettable
static cache (`protected static ?\App\Models\Cart $cachedCart`), cleared via
a `forgetCache()` call at the end of every method that mutates the cart:
`createCart()`, `add()`, `remove()`, `updateQuantity()`, `applyCoupon()`,
`removeCoupon()`, `clear()`. See the class for the exact diff — the shape is
small (a private cache property, a private reset method, one call added per
mutating method) but touches several places in the file.

**On a merge conflict here:** take upstream's version of the file, then
re-apply the manual-cache pattern described above instead of the `once()`
call, and add a `self::forgetCache();` call at the end of each of the six
mutating methods listed.
