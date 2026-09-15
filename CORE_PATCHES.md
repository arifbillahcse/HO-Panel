# Core patches on top of upstream Paymenter

This fork carries deliberate changes to files that ship with upstream
Paymenter (`app/`), rather than living in `extensions/`. Two are bug fixes
with no extension hook to move them out to (section A); the rest is the
domain+hosting unified cart/checkout feature, which by its nature has to
touch the shared checkout flow (section B).

Paymenter's own in-panel "Update" button (Admin → Updates) runs a script that
deletes and replaces the entire `app/` directory on every update — it would
silently wipe both of these. **Don't use that button on this fork.** Update by
merging upstream `master` into the working branch instead (`git fetch` +
`git merge`); a real merge will flag these two files as conflicts instead of
silently discarding the fix, so they never get lost by accident.

## A. Bug fixes (report upstream, drop once fixed there)

Both of these are genuine upstream bugs, not something specific to this
fork's custom extensions — worth reporting to
https://github.com/paymenter/paymenter so they can be fixed for everyone;
once merged upstream, these patches can be dropped entirely.

### 1. `app/Policies/BasePolicy.php`

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

### 2. `app/Classes/Cart.php` — caching bug

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

## B. Domain + hosting unified cart/checkout

Before this, a domain and a hosting product could not be bought in one
checkout — Domain Service had its own separate instant-invoice path,
entirely outside the cart. This feature makes domains a second kind of cart
line (`domain_cart_items`, its own table, owned by the Domain Service
extension) that checks out through the *same* Order and Invoice as hosting
products.

This deliberately couples the files below to
`Paymenter\Extensions\Others\DomainService\Models\DomainCartItem` — a plain
class reference, not a `class_exists()`-guarded one. That's an accepted
tradeoff for this specific fork (domains are a permanent product line here,
not an optional plugin), not a generically safe pattern to copy elsewhere.
If Domain Service is ever removed, every file below needs its domain-related
lines removed too, or it fatals.

**On a merge conflict in any of these:** take upstream's version, then
re-add the specific domain-related lines described below. None of these
are large diffs — each is one or two extra conditions/calls layered onto
upstream logic that otherwise did not change in shape.

### `app/Models/Order.php`
Added `domains()` (`hasMany(Domain::class)`) alongside `services()`, and
extended the `invoices()` attribute to also flatten each domain's own
`DomainInvoice` links in, so Admin → Orders shows the invoice for an order
that contains only domains, or a mix of domains and hosting.

### `app/Classes/Cart.php`
Added `addDomain()`, `removeDomain()`, `domainItems()` — the domain
equivalents of `add()`/`remove()`/`items()`, backed by `DomainCartItem`
instead of `CartItem` since a domain has no product/plan.

### `app/Livewire/Cart.php`
- `updateTotal()`: sums `domainItems()` pricing into the displayed total
  alongside product items.
- `checkout()`: after the existing product-locking loop, re-validates every
  domain line (fresh pricing via `DomainOrderService::resolveForCart()`,
  and a fresh availability check for a new registration — never trust a
  cart for either). After services are created, loops the same resolved
  domains through `DomainOrderService::createForCheckout()` against the
  same `$order`/`$invoice` the hosting items just used.
- Added a `removeDomain($index)` method next to `removeProduct()`.

### `app/Livewire/Components/Cart.php`
The header cart-count badge now adds `domainItems()->count()` so a
domain-only cart still shows the badge.

### `app/Http/Middleware/CheckoutParameterMiddleware.php`
`shouldBlockCurrencyChange()` now also blocks a currency switch while
domain items sit in the cart — domain pricing is currency-dependent exactly
like product pricing, so switching currency mid-cart would leave a stale
price the same way it would for a product.

### `app/Livewire/Components/LocaleSwitch.php`
Same reasoning as the middleware above, for the header currency switcher's
own "cart has items" guard (both in `mount()`'s render-skip condition and
`updatedCurrentCurrency()`'s block).

### Extension-owned files (not core, no merge risk, listed here for context)
- `extensions/Others/DomainService/database/migrations/..._create_domain_cart_items_table.php`
- `extensions/Others/DomainService/database/migrations/..._add_order_id_to_domains_table.php`
- `extensions/Others/DomainService/Models/DomainCartItem.php`
- `extensions/Others/DomainService/Models/Domain.php` — added `order_id` + `order()`
- `extensions/Others/DomainService/Services/DomainOrderService.php` — added
  `resolveForCart()` and `createForCheckout()`; `register()`/`transfer()`
  are untouched and still used by the admin "Order for Customer" page and
  "Import Domain," which don't go through the cart
- `extensions/Others/DomainService/Livewire/Search.php` and `Transfer.php` —
  now add to the cart and redirect there, instead of creating an invoice
  directly
- `themes/ho-theme/views/cart.blade.php` — renders domain lines; themes are
  never touched by Paymenter's updater, so this one is already safe

**After deploying this feature for the first time**, the two new migrations
need to be applied by hand — this extension is already installed, so its
`installed()` hook (which normally runs migrations) will not fire again on
its own:

```bash
php artisan migrate --path=extensions/Others/DomainService/database/migrations
```

## C. Mandatory domain step on hosting checkout

Business rule: hosting cannot be bought without a domain attached — register
one, transfer one in, or point it at one the customer already has. Builds on
section B: this adds a required domain step to the product's own checkout
page, which then feeds the same `Cart::addDomain()` path.

**Scoping, without a new per-product setting:** a hosting server module
(cPanel, DirectAdmin, ...) already declares a checkout-config field named
`domain` — that's how it knows what to provision. This step is shown only
when that field is present on the product, so an SSL/email addon with no
such field is entirely unaffected.

### `app/Livewire/Products/Checkout.php`
- Detects `$productNeedsDomain` from the product's checkout-config schema
  (see above).
- Adds `domainChoice` (register/transfer/existing) plus the fields each
  choice needs, and `domainRules()` for their validation — merged into
  `rules()` only when `$productNeedsDomain` is true.
- The generic checkout-config loops (in `rules()`, `attributes()`, and
  `checkout()`) now skip the `domain` field by name when
  `$productNeedsDomain` — it's driven by the dedicated fields above instead
  of a plain bound input.
- `checkout()`: always writes a real domain name into
  `checkoutConfig['domain']` (every choice needs this — it's what the
  server module reads), and additionally calls `Cart::addDomain()` for
  register/transfer only. "Existing" only sets the config value.
- The `mount()` auto-checkout shortcut (skip the form entirely for a
  single-plan, no-config product) is now also gated on
  `!$productNeedsDomain`, since the domain step is never optional.
- Editing an in-cart item whose product needs a domain always reopens on
  "existing" with the stored name — the original register/transfer choice
  isn't reconstructed, since a paired `DomainCartItem` isn't looked back up.
  Switching back to register/transfer while editing adds a fresh domain
  line rather than reconciling one that might already be in the cart. A
  known limitation, not a bug: acceptable for now, revisit if it causes
  real confusion.

### `themes/default/views/products/checkout.blade.php`
Renders the domain step (not core — themes are never touched by
Paymenter's updater, so this file carries no merge risk). `ho-theme` has no
override for this view, so it inherits from `default`.
