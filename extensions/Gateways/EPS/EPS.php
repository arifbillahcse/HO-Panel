<?php

namespace Paymenter\Extensions\Gateways\EPS;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Gateway;
use App\Enums\InvoiceTransactionStatus;
use App\Exceptions\DisplayException;
use App\Helpers\ExtensionHelper;
use App\Models\Gateway as GatewayModel;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Throwable;

#[ExtensionMeta(
    name: 'EPS',
    description: 'Accept BDT payments through EPS (Easy Payment System) — cards, bKash, Nagad, Rocket and bank channels.',
    version: '1.0.0',
    author: 'Hostorio',
    url: 'https://eps.com.bd',
    icon: 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgd2lkdGg9IjI0IiBoZWlnaHQ9IjI0Ij48ZyBmaWxsPSJub25lIiBzdHJva2U9IndoaXRlIiBzdHJva2Utd2lkdGg9IjEuOCIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cmVjdCB4PSIyLjUiIHk9IjUiIHdpZHRoPSIxOSIgaGVpZ2h0PSIxNCIgcng9IjIiLz48cGF0aCBkPSJNMi41IDEwaDE5Ii8+PHBhdGggZD0iTTYgMTQuNWg0Ii8+PC9nPjwvc3ZnPg==',
)]
class EPS extends Gateway
{
    /**
     * EPS quotes and settles in Bangladeshi Taka only — the API has no
     * currency parameter at all. Charging a USD invoice would silently bill
     * the numeric amount as BDT, so the gateway refuses anything else.
     */
    private const CURRENCY = 'BDT';

    /**
     * Statuses that mean the money moved. EPS documents only "Success"; the
     * rest are accepted defensively because the guideline does not publish a
     * complete enum.
     */
    private const SETTLED = ['success', 'successful', 'completed', 'complete', 'paid'];

    /**
     * Statuses that mean the payment is definitively over. Anything NOT in
     * this list and not settled is treated as still in flight and retried —
     * an unknown status must never be mistaken for a failure.
     */
    private const DEAD = ['failed', 'failure', 'fail', 'cancel', 'cancelled', 'canceled', 'declined', 'rejected', 'expired', 'void', 'timeout'];

    /**
     * How long an unresolved transaction keeps being polled before it is
     * written off as failed.
     */
    private const GIVE_UP_AFTER_HOURS = 24;

    public function boot()
    {
        require __DIR__ . '/routes.php';

        // EPS has no IPN: the ONLY way a result reaches us is the customer's
        // browser coming back. When it doesn't — closed tab, dead battery, a
        // bKash webview that never returns — this sweep settles the payment
        // anyway. It is the difference between a paid invoice and a ticket.
        if (app()->runningInConsole()) {
            Schedule::call(fn () => $this->reconcile())
                ->name('eps-reconcile')
                ->everyTenMinutes()
                ->withoutOverlapping();
        }
    }

    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'username',
                'label' => 'Merchant username',
                'type' => 'text',
                'required' => true,
                'description' => 'The EPS portal login your merchant account was issued with.',
            ],
            [
                'name' => 'password',
                'label' => 'Merchant password',
                'type' => 'text',
                'encrypted' => true,
                'required' => true,
            ],
            [
                'name' => 'hash_key',
                'label' => 'Hash key',
                'type' => 'text',
                'encrypted' => true,
                'required' => true,
                'description' => 'Used to sign every request. Issued by EPS alongside the merchant credentials — never the same as the password.',
            ],
            [
                'name' => 'merchant_id',
                'label' => 'Merchant ID',
                'type' => 'text',
                'required' => true,
                'description' => 'GUID, for example 094980ee-0000-0000-0000-000000000000',
            ],
            [
                'name' => 'store_id',
                'label' => 'Store ID',
                'type' => 'text',
                'required' => true,
                'description' => 'GUID identifying which of your EPS stores the payment belongs to.',
            ],
            [
                'name' => 'transaction_type_id',
                'label' => 'Transaction type ID',
                'type' => 'text',
                'required' => false,
                'description' => 'Leave as 1 (Web) unless EPS tells you otherwise. Their guideline is self-contradictory here — the parameter table says 1=Web, 2=Android, 3=iOS while the sample request sends 10.',
            ],
            [
                'name' => 'sandbox',
                'label' => 'Sandbox mode',
                'type' => 'checkbox',
                'required' => false,
                'description' => 'Send payments to sandboxpgapi.eps.com.bd instead of the live gateway. Sandbox credentials are different from live ones.',
            ],
        ];
    }

    /**
     * Hide EPS from checkout unless the customer is paying in Taka.
     *
     * Paymenter asks this before listing gateways on the cart, on an invoice
     * and on the credit top-up form, so a customer shopping in USD simply
     * never sees EPS rather than picking it and hitting an error.
     *
     * @param  mixed  $total
     * @param  string  $currency
     * @param  string  $type
     * @param  mixed  $items
     */
    public function canUseGateway($total, $currency, $type, $items = []): bool
    {
        return strtoupper((string) $currency) === self::CURRENCY;
    }

    /**
     * Start a payment and hand back the EPS hosted page URL.
     *
     * The currency is checked again here. canUseGateway() already hides EPS
     * from anyone shopping in another currency, but nothing stops an invoice
     * being paid through a stale form or a direct call, and billing a USD
     * amount as Taka would be a hundredfold mistake.
     *
     * @param  mixed  $total
     */
    public function pay(Invoice $invoice, $total)
    {
        if (strtoupper($invoice->currency_code) !== self::CURRENCY) {
            throw new DisplayException('EPS can only accept payments in ' . self::CURRENCY . ', but this invoice is in ' . $invoice->currency_code . '.');
        }

        $customer = $this->customer($invoice);

        $reference = $this->reference();
        $amount = round((float) $total, 2);

        $url = $this->api()->initialize([
            'merchantId' => $this->config('merchant_id'),
            'storeId' => $this->config('store_id'),
            'CustomerOrderId' => 'INV-' . $invoice->id . '-' . substr($reference, -6),
            'merchantTransactionId' => $reference,
            'transactionTypeId' => (int) ($this->config('transaction_type_id') ?: 1),
            'financialEntityId' => 0,
            'transitionStatusId' => 0,
            'totalAmount' => $amount,
            'version' => '1',
            'ipAddress' => request()->ip(),
            'successUrl' => route('extensions.gateways.eps.return', $reference),
            'failUrl' => route('extensions.gateways.eps.return', $reference),
            'cancelUrl' => route('extensions.gateways.eps.return', $reference),
            'customerName' => $customer['name'],
            'customerEmail' => $customer['email'],
            'customerAddress' => $customer['address'],
            'customerAddress2' => $customer['address2'],
            'customerCity' => $customer['city'],
            'customerState' => $customer['state'],
            'customerPostcode' => $customer['zip'],
            'customerCountry' => $customer['country'],
            'customerPhone' => $customer['phone'],
            'productName' => 'Invoice #' . ($invoice->number ?: $invoice->id),
            'productProfile' => 'general',
            'productCategory' => 'Hosting',
            // Passthrough fields survive the round trip, so the invoice can
            // still be identified from EPS's own records alone.
            'ValueA' => (string) $invoice->id,
        ]);

        // Only once EPS has accepted the payment. This row is what the
        // reconciliation sweep walks, so it has to exist before the customer
        // is redirected — but recording an attempt EPS rejected would leave a
        // phantom failure against an invoice nobody tried to pay.
        ExtensionHelper::addProcessingPayment($invoice, 'EPS', $amount, transactionId: $reference);

        return $url;
    }

    /**
     * A fresh merchant transaction reference.
     *
     * EPS wants a unique numeric string of at least ten digits; seventeen
     * matches the length of their own documented example. Uniqueness is
     * checked rather than assumed, since two customers can start a payment in
     * the same second.
     */
    private function reference(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = now()->format('YmdHis') . random_int(100, 999);

            if (!InvoiceTransaction::where('transaction_id', $reference)->exists()) {
                return $reference;
            }
        }

        throw new DisplayException('Could not start the payment just now. Please try again.');
    }

    /**
     * Where the customer lands after paying, failing or cancelling.
     *
     * The query string EPS appends here is attacker-controlled — anyone can
     * visit this URL with ?Status=Success — so it is ignored entirely. Only
     * the server-to-server verification below decides anything.
     */
    public function returned(Request $request, string $reference)
    {
        $transaction = InvoiceTransaction::where('transaction_id', $reference)->first();

        try {
            if ($transaction) {
                $this->settle($transaction);
                $invoiceId = $transaction->invoice_id;
            } else {
                $invoiceId = $this->recover($reference);
            }
        } catch (Throwable $e) {
            // The sweep will pick it up; never show the customer a stack trace
            // over what may well be a successful payment.
            Log::error('EPS: could not verify ' . $reference . ' on return: ' . $e->getMessage());

            $invoiceId = $transaction?->invoice_id;
        }

        return $invoiceId
            ? redirect()->route('invoices.show', $invoiceId)
            : redirect()->route('invoices');
    }

    /**
     * Settle a payment we have no local record of.
     *
     * This should never happen — pay() writes the row immediately after EPS
     * accepts the payment — but if that write ever failed, the customer would
     * have paid with nothing left to reconcile against. EPS echoes the invoice
     * id back in ValueA, so the payment can still be recovered from its own
     * records. Nothing here trusts the request: the verification call decides.
     *
     * @return int|null  The invoice to send the customer to, if identifiable.
     */
    private function recover(string $reference): ?int
    {
        $api = $this->api();
        $result = $api->verify($reference);

        if (!$result) {
            return null;
        }

        $invoiceId = (int) $api->get($result, 'ValueA');
        $invoice = $invoiceId ? Invoice::find($invoiceId) : null;

        if (!$invoice) {
            Log::error("EPS: {$reference} verified but no invoice could be identified from it.");

            return null;
        }

        if (in_array(strtolower(trim((string) $api->get($result, 'Status'))), self::SETTLED, true)) {
            Log::warning("EPS: recovered orphaned payment {$reference} for invoice {$invoice->id}.");

            ExtensionHelper::addPayment(
                $invoice->id,
                'EPS',
                (float) $api->get($result, 'TotalAmount'),
                transactionId: $reference,
            );
        }

        return $invoice->id;
    }

    /**
     * Ask EPS what actually happened and record it.
     *
     * Idempotent: addPayment() keys on the transaction id, so the return trip
     * and the sweep racing each other cannot double-credit an invoice.
     */
    public function settle(InvoiceTransaction $transaction): void
    {
        if ($transaction->status === InvoiceTransactionStatus::Succeeded) {
            return;
        }

        $reference = $transaction->transaction_id;
        $result = $this->api()->verify($reference);

        if (!$result) {
            $this->expireIfStale($transaction, 'EPS has no record of it');

            return;
        }

        $api = $this->api();
        $status = strtolower(trim((string) $api->get($result, 'Status')));

        if (in_array($status, self::SETTLED, true)) {
            // The amount comes from the verified server response, not the
            // browser, so it is safe to trust — and safer to record than the
            // amount we asked for, since a short payment should leave the
            // invoice partially unpaid rather than silently closed.
            $paid = (float) ($api->get($result, 'TotalAmount') ?? $transaction->amount);

            if (abs($paid - (float) $transaction->amount) > 0.009) {
                Log::warning("EPS: {$reference} settled {$paid} against an expected {$transaction->amount}.");
            }

            ExtensionHelper::addPayment(
                $transaction->invoice_id,
                'EPS',
                $paid,
                transactionId: $reference,
            );

            return;
        }

        if (in_array($status, self::DEAD, true)) {
            ExtensionHelper::addFailedPayment(
                $transaction->invoice_id,
                'EPS',
                $transaction->amount,
                transactionId: $reference,
            );

            return;
        }

        // Unknown status. EPS does not publish the full list, so treat it as
        // still running rather than guessing — the sweep will ask again.
        $this->expireIfStale($transaction, "unresolved status '{$status}'");
    }

    /**
     * Settle every EPS payment still in flight.
     *
     * Skips the first two minutes so a customer mid-payment is not marked
     * failed while they are still typing their OTP.
     */
    public function reconcile(): void
    {
        $gateway = GatewayModel::where('extension', 'EPS')->first();

        if (!$gateway) {
            return;
        }

        InvoiceTransaction::query()
            ->where('gateway_id', $gateway->id)
            ->where('status', InvoiceTransactionStatus::Processing)
            ->where('created_at', '<', now()->subMinutes(2))
            ->where('created_at', '>', now()->subDays(7))
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->each(function (InvoiceTransaction $transaction) {
                try {
                    $this->settle($transaction);
                } catch (Throwable $e) {
                    Log::error('EPS: reconciliation failed for ' . $transaction->transaction_id . ': ' . $e->getMessage());
                }
            });
    }

    /**
     * Stop polling a transaction that has been unresolved for a day. Until
     * then it stays Processing, because an unknown status is not a failure.
     */
    private function expireIfStale(InvoiceTransaction $transaction, string $why): void
    {
        if ($transaction->created_at->gt(now()->subHours(self::GIVE_UP_AFTER_HOURS))) {
            return;
        }

        Log::info("EPS: writing off {$transaction->transaction_id} after " . self::GIVE_UP_AFTER_HOURS . "h — {$why}.");

        ExtensionHelper::addFailedPayment(
            $transaction->invoice_id,
            'EPS',
            $transaction->amount,
            transactionId: $transaction->transaction_id,
        );
    }

    /**
     * EPS marks name, address, city, state, postcode, country and phone all
     * mandatory. Paymenter seeds every one of them as a required custom
     * property, so the data is normally there — but a client created before
     * those were required, or through the API, can still be missing one.
     * Failing here with a readable message beats an opaque EPS rejection.
     */
    private function customer(Invoice $invoice): array
    {
        $user = $invoice->user;
        $properties = $user->properties()->pluck('value', 'key')->toArray();

        $customer = [
            'name' => trim($user->name) ?: $user->email,
            'email' => $user->email,
            'address' => $properties['address'] ?? null,
            'address2' => $properties['address2'] ?? '',
            'city' => $properties['city'] ?? null,
            'state' => $properties['state'] ?? null,
            'zip' => $properties['zip'] ?? null,
            'country' => $properties['country'] ?? null,
            'phone' => $properties['phone'] ?? null,
        ];

        $missing = array_keys(array_filter(
            array_diff_key($customer, ['address2' => null]),
            fn ($value) => $value === null || trim((string) $value) === '',
        ));

        if ($missing) {
            throw new DisplayException('Please add your ' . implode(', ', $missing) . ' to your account details before paying with EPS — EPS requires them for every transaction.');
        }

        return $customer;
    }

    private function api(): EpsApi
    {
        return new EpsApi(
            (string) $this->config('username'),
            (string) $this->config('password'),
            (string) $this->config('hash_key'),
            (bool) $this->config('sandbox'),
        );
    }
}
