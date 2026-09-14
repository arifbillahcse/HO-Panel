<div class="container mt-14">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">Domains</h1>
            <p class="text-base/60 mt-2">Every domain registered through your account.</p>
        </div>
        <a href="{{ route('cosmotown.search') }}" wire:navigate class="shrink-0">
            <span class="flex items-center gap-2 justify-center bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2.5 px-5 rounded-md duration-300">
                <x-ri-add-fill class="size-4" />
                Register a domain
            </span>
        </a>
    </div>

    @forelse ($domains as $service)
        @php
            $name = $service->properties->firstWhere('key', 'domain')?->value;
            $registered = $service->properties->contains('key', 'cosmotown_registered_at');
        @endphp

        <div class="card p-5 mt-4 flex flex-col lg:flex-row lg:items-center justify-between gap-5">

            <div class="min-w-0">
                <div class="flex items-center gap-2.5 flex-wrap">
                    @if ($service->status === 'active')
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-success/15 text-success text-xs font-semibold px-2.5 py-1">
                            <x-ri-checkbox-circle-fill class="size-3.5" />
                            Active
                        </span>
                    @elseif ($service->status === 'suspended')
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-inactive/15 text-inactive text-xs font-semibold px-2.5 py-1">
                            <x-ri-forbid-fill class="size-3.5" />
                            Suspended
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/15 text-warning text-xs font-semibold px-2.5 py-1">
                            <x-ri-error-warning-fill class="size-3.5" />
                            {{ ucfirst($service->status) }}
                        </span>
                    @endif

                    <h2 class="text-lg font-semibold truncate">{{ $name ?? $service->label }}</h2>
                </div>

                <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 mt-2.5 text-sm text-base/60">
                    @if ($service->expires_at)
                        <span>
                            Renews {{ $service->expires_at->format('M d, Y') }}
                            <span class="text-base/40">({{ $service->expires_at->diffForHumans() }})</span>
                        </span>
                    @endif
                    <span class="flex items-center gap-2">
                        <x-ri-circle-fill class="size-1 text-base/20" />
                        {{ $service->formattedPrice }}
                    </span>
                </div>

                @unless ($registered)
                    <p class="text-sm text-warning mt-2.5">Registration has not completed yet.</p>
                @endunless
            </div>

            <div class="flex flex-wrap items-center gap-3 shrink-0">
                <a href="{{ route('services.show', $service) }}" wire:navigate
                    class="text-sm font-semibold text-primary hover:text-primary/80">
                    Billing
                </a>
                @if ($registered)
                    <a href="{{ route('cosmotown.domains.show', $service) }}" wire:navigate>
                        <span class="flex items-center gap-2 justify-center bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2.5 px-5 rounded-md duration-300 whitespace-nowrap">
                            <x-ri-settings-3-line class="size-4" />
                            Manage
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
            <a href="{{ route('cosmotown.search') }}" wire:navigate class="inline-block mt-5">
                <span class="flex items-center gap-2 justify-center bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2.5 px-4.5 rounded-md duration-300">
                    Find a domain
                    <x-ri-arrow-right-fill class="size-4" />
                </span>
            </a>
        </div>
    @endforelse

</div>
