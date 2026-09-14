<div class="container mt-14">

    <h1 class="text-3xl font-bold">Find your domain</h1>
    <p class="text-base/60 mt-2">Search once and see every extension we offer, with the price you'll pay.</p>

    <form wire:submit="search" class="mt-8 flex flex-col sm:flex-row gap-3">
        <label for="domain-term" class="sr-only">Domain name</label>
        <input
            id="domain-term"
            type="text"
            wire:model="term"
            placeholder="yourname"
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            class="flex-grow bg-background-secondary border border-neutral rounded-md px-4 py-3 text-base placeholder:text-base/40 focus:outline-none focus:ring-2 focus:ring-primary"
        >
        <button
            type="submit"
            class="flex items-center gap-2 justify-center bg-primary text-white font-semibold hover:bg-primary/80 py-3 px-6 rounded-md duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove wire:target="search">Search</span>
            <span wire:loading wire:target="search">Checking…</span>
        </button>
    </form>

    @if ($notice)
        <div class="mt-6 flex items-start gap-3 rounded-lg border border-warning/30 bg-warning/10 p-4">
            <x-ri-error-warning-fill class="size-5 text-warning shrink-0 mt-0.5" />
            <p class="text-sm">{{ $notice }}</p>
        </div>
    @endif

    {{-- Placeholder rows while the lookups run, so the page does not jump. --}}
    <div wire:loading wire:target="search" class="mt-8 flex flex-col gap-3">
        @for ($i = 0; $i < 4; $i++)
            <div class="card p-5 flex items-center justify-between animate-pulse">
                <div class="h-4 w-48 rounded bg-neutral"></div>
                <div class="h-9 w-28 rounded bg-neutral"></div>
            </div>
        @endfor
    </div>

    <div wire:loading.remove wire:target="search">
        @if ($results)
            <div class="mt-8 flex flex-col gap-3">
                @foreach ($results as $result)
                    <div class="card p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">

                        <div class="flex items-center gap-3 min-w-0">
                            @if ($result['status'] === 'available')
                                <x-ri-checkbox-circle-fill class="size-5 text-success shrink-0" />
                            @elseif ($result['status'] === 'taken')
                                <x-ri-forbid-fill class="size-5 text-inactive shrink-0" />
                            @else
                                <x-ri-error-warning-fill class="size-5 text-warning shrink-0" />
                            @endif

                            <div class="min-w-0">
                                <p class="font-semibold truncate {{ $result['status'] === 'taken' ? 'text-base/50' : '' }}">
                                    {{ $result['domain'] }}
                                </p>
                                <p class="text-sm text-base/60">
                                    @if ($result['status'] === 'available')
                                        Available
                                    @elseif ($result['status'] === 'taken')
                                        Already registered
                                    @else
                                        We couldn't check this one just now
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 shrink-0">
                            @if ($result['price'])
                                <span class="font-semibold whitespace-nowrap {{ $result['status'] === 'available' ? '' : 'text-base/40' }}">
                                    {{ $result['price'] }}
                                </span>
                            @endif

                            @if ($result['status'] === 'available')
                                <a href="{{ $result['url'] }}" wire:navigate>
                                    <span class="flex items-center gap-2 justify-center bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2.5 px-5 rounded-md duration-300 whitespace-nowrap">
                                        Register
                                        <x-ri-arrow-right-fill class="size-4" />
                                    </span>
                                </a>
                            @elseif ($result['status'] === 'unknown')
                                <button type="button" wire:click="search"
                                    class="text-sm font-semibold text-primary hover:text-primary/80 whitespace-nowrap">
                                    Try again
                                </button>
                            @endif
                        </div>

                    </div>
                @endforeach
            </div>

            <p class="text-sm text-base/50 mt-6">
                Availability comes from the domain registry. A name can still be taken between checking and ordering.
            </p>
        @elseif ($searched && !$notice)
            <div class="card p-10 text-center mt-8">
                <p class="text-base/60">Nothing to show for that search.</p>
            </div>
        @endif
    </div>

</div>
