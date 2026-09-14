<?php

namespace Paymenter\Extensions\Others\DomainService\Livewire;

use App\Exceptions\DisplayException;
use App\Livewire\Component;
use App\Models\Currency;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\DomainService\Models\DomainTld;
use Paymenter\Extensions\Others\DomainService\Services\DomainOrderService;
use Paymenter\Extensions\Others\DomainService\Support\DomainAvailability;
use Throwable;

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

        try {
            $invoice = app(DomainOrderService::class)->register(
                Auth::user(),
                $label . '.' . $tld,
                $this->currency(),
            );
        } catch (DisplayException $e) {
            $this->notify($e->getMessage(), 'error');

            return null;
        } catch (Throwable $e) {
            report($e);
            $this->notify('We could not start that order. Please try again.', 'error');

            return null;
        }

        return $this->redirect(route('invoices.show', $invoice) . '?pay=true', true);
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
