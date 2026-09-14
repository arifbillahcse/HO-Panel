<?php

namespace Paymenter\Extensions\Servers\Cosmotown\Livewire\Domains;

use App\Livewire\Component;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;

class Index extends Component
{
    public function render()
    {
        // Only registrar-backed services belong here; hosting stays on the
        // Services page. Statuses other than cancelled are kept so a suspended
        // or pending domain is visible rather than silently missing.
        $domains = Service::query()
            ->where('user_id', Auth::id())
            ->where('status', '!=', Service::STATUS_CANCELLED)
            ->whereHas('product.server', fn ($query) => $query->where('extension', 'Cosmotown'))
            ->with(['properties', 'product'])
            ->orderBy('expires_at')
            ->get();

        return view('cosmotown::domains.index', [
            'domains' => $domains,
        ])->layoutData([
            'title' => 'Domains',
            'sidebar' => true,
        ]);
    }
}
