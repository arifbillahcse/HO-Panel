<div class="container mt-14">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">Domains</h1>
            <p class="text-base/60 mt-2">Every domain registered through your account.</p>
        </div>
        <a href="{{ route('domainservice.search') }}" wire:navigate class="shrink-0">
            <span class="flex items-center gap-2 justify-center bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2.5 px-5 rounded-md duration-300">
                <x-ri-add-fill class="size-4" /> Register a domain
            </span>
        </a>
    </div>

    @forelse ($domains as $domain)
        <div class="card p-5 mt-4 flex flex-col lg:flex-row lg:items-center justify-between gap-5">
            <div class="min-w-0">
                <div class="flex items-center gap-2.5 flex-wrap">
                    @if ($domain->status === 'active')
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-success/15 text-success text-xs font-semibold px-2.5 py-1">
                            <x-ri-checkbox-circle-fill class="size-3.5" /> Active
                        </span>
                    @elseif (in_array($domain->status, ['pending', 'transfer_pending']))
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/15 text-warning text-xs font-semibold px-2.5 py-1">
                            <x-ri-error-warning-fill class="size-3.5" /> {{ str($domain->status)->headline() }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-inactive/15 text-inactive text-xs font-semibold px-2.5 py-1">
                            <x-ri-forbid-fill class="size-3.5" /> {{ str($domain->status)->headline() }}
                        </span>
                    @endif
                    <h2 class="text-lg font-semibold truncate">{{ $domain->name }}</h2>
                </div>
                @if ($domain->expires_at)
                    <p class="text-sm text-base/60 mt-2.5">
                        Renews {{ $domain->expires_at->format('M d, Y') }}
                        <span class="text-base/40">({{ $domain->expires_at->diffForHumans() }})</span>
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-3 shrink-0">
                @if ($domain->status === 'active')
                    <a href="{{ route('domainservice.domains.show', $domain) }}" wire:navigate>
                        <span class="flex items-center gap-2 justify-center bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2.5 px-5 rounded-md duration-300 whitespace-nowrap">
                            <x-ri-settings-3-line class="size-4" /> Manage
                        </span>
                    </a>
                @endif
            </div>
        </div>
    @empty
        <div class="card p-10 text-center mt-8">
            <div class="bg-background border border-neutral p-3 rounded-lg w-fit mx-auto">
                <x-ri-global-line class="size-6 text-base/40" />
            </div>
            <h2 class="text-lg font-semibold mt-4">No domains yet</h2>
            <p class="text-sm text-base/60 mt-1.5 max-w-sm mx-auto">Search for a name and it will appear here once registered.</p>
            <a href="{{ route('domainservice.search') }}" wire:navigate class="inline-block mt-5">
                <span class="flex items-center gap-2 justify-center bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2.5 px-4.5 rounded-md duration-300">
                    Find a domain <x-ri-arrow-right-fill class="size-4" />
                </span>
            </a>
        </div>
    @endforelse

    <div class="mt-6">{{ $domains->links() }}</div>
</div>
