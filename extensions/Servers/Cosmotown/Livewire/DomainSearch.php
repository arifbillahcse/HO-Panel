<?php

namespace Paymenter\Extensions\Servers\Cosmotown\Livewire;

use App\Classes\Price;
use App\Livewire\Component;
use App\Models\Product;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Servers\Cosmotown\DomainAvailability;

class DomainSearch extends Component
{
    /** Kept in the URL so a search can be linked to and survives a refresh. */
    #[Url(as: 'q')]
    public string $term = '';

    public array $results = [];

    public bool $searched = false;

    /** Admin-facing setup problem, shown instead of empty results. */
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

        $label = $this->normaliseLabel($this->term);

        if ($label === null) {
            $this->notice = 'Enter a name using letters, numbers and hyphens.';

            return;
        }

        $offers = $this->offers();

        if ($offers->isEmpty()) {
            $this->notice = 'No domain extensions are configured for sale yet.';

            return;
        }

        // Put the exact extension the customer typed first — if someone asks
        // for "example.org" the .org result should not be fourth in the list.
        $typedTld = $this->typedTld($this->term);
        if ($typedTld) {
            $offers = $offers->sortByDesc(fn ($offer) => $offer['tld'] === $typedTld)->values();
        }

        $domains = $offers->map(fn ($offer) => $label . $offer['tld'])->all();

        $availability = (new DomainAvailability)->check($domains);

        foreach ($offers as $offer) {
            $domain = $label . $offer['tld'];

            $this->results[] = [
                'domain' => $domain,
                'tld' => $offer['tld'],
                'status' => $availability[$domain] ?? DomainAvailability::UNKNOWN,
                'price' => $offer['price'],
                'url' => $offer['url'] . '&' . http_build_query(['config' => ['domain' => $domain]]),
            ];
        }
    }

    /**
     * Every TLD on sale, with its price and a prefilled checkout link.
     *
     * Built from the products this registrar serves rather than a list kept
     * inside the extension, so pricing stays where the rest of Paymenter's
     * pricing lives and there is nothing to keep in sync.
     */
    private function offers(): Collection
    {
        $products = Product::query()
            ->whereHas('server', fn ($query) => $query->where('extension', 'Cosmotown'))
            ->where('hidden', false)
            ->with(['configOptions.children', 'plans', 'category'])
            ->get();

        $offers = collect();

        foreach ($products as $product) {
            $plan = $product->plans->first();
            $category = $product->category;

            if (!$plan || !$category) {
                continue;
            }

            $option = $product->configOptions
                ->firstWhere(fn ($o) => strtolower((string) $o->env_variable) === 'tld');

            if (!$option) {
                continue;
            }

            foreach ($option->children as $value) {
                $tld = $this->normaliseTld($value->name);

                if (!$tld || $offers->contains(fn ($existing) => $existing['tld'] === $tld)) {
                    continue;
                }

                $offers->push([
                    'tld' => $tld,
                    'price' => $this->price($plan, $value),
                    'url' => route('products.checkout', [
                        'category' => $category->slug,
                        'product' => $product->slug,
                    ]) . '?' . http_build_query([
                        'plan' => $plan->id,
                        'options' => [$option->id => $value->id],
                    ]),
                ]);
            }
        }

        return $offers->values();
    }

    /**
     * Mirrors Checkout::updatePricing so the figure shown here is the figure
     * charged — plan price plus the extension's own price, taxed the same way.
     */
    private function price($plan, $value): string
    {
        try {
            $base = $plan->price()->price;

            $extra = $value->price(
                billing_period: $plan->billing_period,
                billing_unit: $plan->billing_unit,
            )->price ?? 0;

            return (string) new Price([
                'price' => $base + $extra,
                'currency' => $plan->price()->currency,
            ], apply_exclusive_tax: true);
        } catch (\Throwable $e) {
            // A TLD priced in a currency the visitor is not using should drop
            // out of the results quietly, not break the whole search.
            return '';
        }
    }

    /**
     * Reduce whatever was typed to the label left of the first dot.
     * "Example.COM", "example", "https://example.com/x" all give "example".
     */
    private function normaliseLabel(string $term): ?string
    {
        $term = strtolower(trim($term));
        $term = preg_replace('#^https?://#', '', $term);
        $term = explode('/', $term)[0];
        $label = explode('.', $term)[0];

        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $label)) {
            return null;
        }

        return $label;
    }

    private function typedTld(string $term): ?string
    {
        $term = strtolower(trim($term));

        if (!str_contains($term, '.')) {
            return null;
        }

        return $this->normaliseTld(substr($term, strpos($term, '.')));
    }

    private function normaliseTld(?string $tld): ?string
    {
        $tld = strtolower(trim((string) $tld));

        if ($tld === '') {
            return null;
        }

        if (!str_starts_with($tld, '.')) {
            $tld = '.' . $tld;
        }

        return preg_match('/^\.[a-z0-9.-]+$/', $tld) ? $tld : null;
    }

    public function render()
    {
        return view('cosmotown::search')->layoutData([
            'title' => 'Domains',
        ]);
    }
}
