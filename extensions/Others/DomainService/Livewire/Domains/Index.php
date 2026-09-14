<?php

namespace Paymenter\Extensions\Others\DomainService\Livewire\Domains;

use App\Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\DomainService\Models\Domain;

class Index extends Component
{
    public function render()
    {
        $domains = Domain::query()
            ->where('user_id', Auth::id())
            ->where('status', '!=', Domain::STATUS_CANCELLED)
            ->orderBy('expires_at')
            ->paginate(config('settings.pagination', 20));

        return view('domainservice::domains.index', ['domains' => $domains])
            ->layoutData(['title' => 'Domains', 'sidebar' => true]);
    }
}
