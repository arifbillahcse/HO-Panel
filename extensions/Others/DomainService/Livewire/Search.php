<?php

namespace Paymenter\Extensions\Others\DomainService\Livewire;

use App\Classes\Cart;
use App\Exceptions\DisplayException;
use App\Livewire\Component;
use App\Models\Currency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\DomainService\Models\DomainTld;
use Paymenter\Extensions\Others\DomainService\Support\DomainAvailability;

class Search extends Component
{
    #[Url(as: 'q')]
    public string $term = '';

    public array $results = [];

    public bool $searched = false;

    public ?string $notice = null;

    public function mount(): void
    {
        if (trim($this->term) !== '') {
            $this->search();
        }
    }

    public function search(): void
    {
        // Search triggers outbound RDAP lookups, so cap it per client to stop
        // the public page being used as a lookup amplifier.
        $key = 'domainsearch:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->notice = 'Too many searches. Please wait a moment and try again.';

            return;
        }
        RateLimiter::hit($key, 60);

        $this->results = [];
        $this->notice = null;
        $this->searched = true;

        $label = $this->label($this->term);

        if ($label === null) {
            $this->notice = 'Enter a name using letters, numbers and hyphens.';

            return;
        }

        $offers = $this->offers();

        if ($offers->isEmpty()) {
            $this->notice = 'No domain extensions are on sale yet.';

            return;
        }

        $names = $offers->map(fn ($o) => $label . '.' . $o['tld'])->all();
        $availability = (new DomainAvailability)->check($names);

        foreach ($offers as $offer) {
            $name = $label . '.' . $offer['tld'];
            $this->results[] = [
                'name' => $name,
                'tld' => $offer['tld'],
                'price' => $offer['price'],
                'status' => $availability[$name] ?? DomainAvailability::UNKNOWN,
            ];
        }
    }

    public function register(string $tld): mixed
    {
        if (!Auth::check()) {
            return redirect()->guest(route('login'));
        }

        $label = $this->label($this->term);

        if ($label === null) {
            $this->notify('Please search for a domain first.', 'error');

            return null;
        }

        // Cap orders per customer so a signed-in account cannot spam pending
        // domains and unpaid invoices.
        $key = 'domainorder:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->notify('Too many attempts. Please wait a minute and try again.', 'error');

            return null;
        }
        RateLimiter::hit($key, 60);

        $name = $label . '.' . $tld;

        // Do not take money for a domain that is already registered elsewhere.
        // Only a definite "taken" blocks — an RDAP hiccup (unknown) must not
        // stop a legitimate order. Checked again for real at checkout, since
        // availability can change while it sits in the cart.
        if ((new DomainAvailability)->check([$name])[$name] === DomainAvailability::TAKEN) {
            $this->notify('That domain was just taken. Please choose another.', 'error');

            return null;
        }

        try {
            Cart::addDomain($name, $tld, 'register', 1);
        } catch (DisplayException $e) {
            $this->notify($e->getMessage(), 'error');

            return null;
        }

        return $this->redirect(route('cart'), true);
    }

    /** Offered TLDs priced in the visitor's currency. */
    private function offers(): \Illuminate\Support\Collection
    {
        $currency = $this->currencyModel();

        return DomainTld::where('enabled', true)
            ->with(['pricing', 'registrar'])
            ->get()
            ->map(function (DomainTld $tld) use ($currency) {
                $price = $tld->priceFor($currency->code);

                if (!$price) {
                    return null;
                }

                return [
                    'tld' => $tld->tld,
                    'price' => $this->format($currency, (float) $price->register_price),
                ];
            })
            ->filter()
            ->sortBy('tld')
            ->values();
    }

    private function label(string $term): ?string
    {
        $term = strtolower(trim($term));
        $term = preg_replace('#^https?://#', '', $term);
        $term = explode('/', $term)[0];
        $label = explode('.', $term)[0];

        return preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $label) ? $label : null;
    }

    private function currency(): string
    {
        return $this->currencyModel()->code;
    }

    private function currencyModel(): Currency
    {
        $code = session('currency', config('settings.default_currency'));

        return Currency::find($code) ?? Currency::query()->first() ?? new Currency(['code' => 'USD']);
    }

    private function format(Currency $currency, float $amount): string
    {
        return ($currency->prefix ?? '') . number_format($amount, 2) . ($currency->suffix ?? '');
    }

    public function render()
    {
        return view('domainservice::search')->layoutData(['title' => 'Domains']);
    }
}
