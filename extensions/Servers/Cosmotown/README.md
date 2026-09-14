# Cosmotown for Paymenter

Registers and renews domain names through the Cosmotown reseller API.

## What works

| | |
|---|---|
| Register on order | yes |
| Renew on invoice payment | yes, automatic |
| Nameservers at registration | yes |
| Registrar lock | yes, customer-toggleable |
| WHOIS privacy | yes, customer-toggleable |
| Nameserver editing | yes, in the client area |
| Availability search | yes, at `/domains` |
| Client-area domain list | yes, at `/domains/manage` |
| Transfers in | yes, with status polling |
| EPP codes | no — endpoint unverified |
| WHOIS contact edits | no — endpoint unverified |
| Child nameservers | no — Cosmotown exposes no endpoint |

## Setup

**1. Add the server**

Admin → Servers → Create, choose **Cosmotown**, then:

- **Reseller API key** — Cosmotown → My Account → Reseller API. The key only, never your password.
- **Sandbox mode** — tick this while testing. It sends everything to Cosmotown's sandbox, so no real domains are registered and nothing is billed. Use a sandbox API key when it's on.

Press **Test Connection** before saving.

**2. Create the product**

Admin → Products → Create. Assign the Cosmotown server, then set:

- **Registration period (years)** — usually 1. Renewals extend by the same amount.
- **Default nameservers** — comma separated, e.g. `ns1.hostorio.com, ns2.hostorio.com`. Leave blank to keep Cosmotown's.

Give it a **yearly recurring** plan so Paymenter raises renewal invoices on schedule.

**3. Price your TLDs**

Cosmotown's API publishes no wholesale price list, so retail prices are yours to
set. Either layout works — search picks up both automatically.

*A product per TLD.* Name the product after the extension (`.com`, `.net`) and
put the price on its plan. Simplest to reason about, and each TLD gets its own
description, image and stock limit.

*One product, many TLDs.* Add a **Select** configurable option with
**Environment Variable** set to exactly `tld`, one value per extension with its
own price:

```
.com   1,400 BDT
.net   1,600 BDT
.org   1,500 BDT
```

Less admin work once you sell more than a handful. Config option prices are
included in renewal invoices as well as the first one.

## Domain search

A public search page is added at **`/domains`**, linked from the storefront
navigation. Customers type a name, see every TLD you sell with its price, and
click through to a checkout with the domain already filled in.

The TLD list and prices come from the `TLD` configurable option above — there
is no separate list inside the extension to keep in sync. Add a TLD there and
it appears in search immediately.

**Availability is resolved over RDAP**, the registry protocol, not the
Cosmotown API. Cosmotown's own client does the same. That means searches spend
no API quota and cannot trip your reseller rate limit, and search keeps working
regardless of what Cosmotown does to its endpoints. Answers are cached for ten
minutes; failures are never cached and show as "couldn't check" rather than
claiming a taken domain is free.

If search shows *"No domain extensions are configured for sale yet"*, the
product either has no `TLD` option attached or its `env_variable` is not
exactly `tld`.

## Client area

A **Domains** entry appears in the customer sidebar, between Services and
Invoices.

**`/domains/manage`** lists their domains with status, renewal date and price.

**`/domains/manage/{service}`** is the management screen: edit up to five
nameservers, toggle the registrar lock, toggle WHOIS privacy. Access is gated
by Paymenter's own `can:view,service`, so a customer cannot open someone
else's domain by changing the id.

Registrar state is read live (cached five minutes) and the cache is cleared
whenever a change is saved, so the page never shows a stale value after an
edit. If Cosmotown is unreachable the page says so rather than showing blank
fields that would look like a domain with no nameservers.

Billing for a domain stays on the normal service page — the Domains pages
handle the registrar side only.

## Transfers

Set a product's **Order type** to *Transfer in* and checkout asks for the
authorisation (EPP) code alongside the domain. The code is used once to start
the transfer and then deleted — it is worthless afterwards and should not sit
in your database.

Transfers do not complete immediately. Cosmotown sends no callback, so the
extension polls `domainstatus` **hourly** and finishes the job when the
registry reports COMPLETE: the domain becomes manageable, your default
nameservers are applied, and you get an email.

A failure also emails you, with the reason. That matters because the customer
has paid and holds nothing — usually the domain is locked at the losing
registrar, the auth code is wrong, or it was registered under 60 days ago.

Polling needs the Paymenter scheduler cron to be installed:

```
* * * * * cd /path/to/paymenter && php artisan schedule:run >> /dev/null 2>&1
```

Without it, transfers will start but never be marked complete.

## How renewal works

Paymenter raises a renewal invoice before expiry. When it is paid, `Invoice\Paid` fires and this extension calls `renewdomains`.

Paymenter moves `expires_at` forward on payment **regardless of whether the registrar call succeeded**. So if Cosmotown rejects the renewal, the panel would show the domain as renewed while it quietly lapses months later. To make that impossible to miss, a failure sends an email to your system address and writes an error to the log. Watch for those.

A repeated `Invoice\Paid` for the same invoice is ignored, so a redelivered event cannot buy a second year at your cost.

## Suspension and termination

**Suspend** sets the registrar lock. Domains have no real suspension; this at least prevents a transfer out while an invoice is unpaid.

**Terminate** detaches the domain in Paymenter and does nothing at Cosmotown. There is no delete endpoint, and deleting a paid-up domain over an unpaid invoice would destroy something the customer owns. It lapses at its own expiry date instead.

## Security note

Paymenter logs every outbound HTTP call to **Admin → HTTP logs** when debug mode is on, and its redaction list covers only `authorization` and `password` headers. Cosmotown authenticates with `X-API-TOKEN`, which is **not** redacted — so your reseller API key will appear in those logs in plain text.

Keep debug mode off in production, and rotate the key if you have had it on.

## Rate limits

`getActions()` reads live domain state for the service page, cached for five minutes so page refreshes don't spend API quota. The lock and privacy toggles clear that cache so changes appear immediately.
