<div class="container mt-14">
    <div class="max-w-3xl mx-auto text-center">
        <h1 class="text-3xl font-bold">Find your domain</h1>
        <p class="text-base/60 mt-2">Search for a name and register it in a minute.</p>

        <form wire:submit="search" class="mt-6 flex flex-col sm:flex-row gap-3">
            <input type="text" wire:model="term" autofocus autocomplete="off" spellcheck="false"
                placeholder="yourbusiness"
                class="flex-1 bg-background-secondary border border-neutral rounded-md px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
            <button type="submit"
                class="flex items-center gap-2 justify-center bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-3 px-6 rounded-md duration-300"
                wire:loading.attr="disabled" wire:target="search">
                <span wire:loading.remove wire:target="search">Search</span>
                <span wire:loading wire:target="search">Searching…</span>
            </button>
        </form>
    </div>

    @if ($notice)
        <div class="max-w-3xl mx-auto mt-6 card p-4 text-sm text-base/70 flex items-center gap-2">
            <x-ri-error-warning-fill class="size-4 text-warning" />
            {{ $notice }}
        </div>
    @endif

    @if ($searched && $results)
        <div class="max-w-3xl mx-auto mt-6 flex flex-col gap-3">
            @foreach ($results as $result)
                <div class="card p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        @if ($result['status'] === 'available')
                            <x-ri-checkbox-circle-fill class="size-5 text-success shrink-0" />
                        @elseif ($result['status'] === 'taken')
                            <x-ri-forbid-fill class="size-5 text-inactive shrink-0" />
                        @else
                            <x-ri-error-warning-fill class="size-5 text-warning shrink-0" />
                        @endif
                        <span class="font-semibold truncate">{{ $result['name'] }}</span>
                    </div>

                    <div class="flex items-center gap-4 shrink-0">
                        @if ($result['status'] === 'available')
                            <span class="text-sm font-semibold">{{ $result['price'] }}</span>
                            <button type="button" wire:click="register('{{ $result['tld'] }}')"
                                wire:loading.attr="disabled" wire:target="register('{{ $result['tld'] }}')"
                                class="flex items-center gap-1.5 bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2 px-4 rounded-md duration-300 whitespace-nowrap">
                                <x-ri-add-fill class="size-4" /> Register
                            </button>
                        @elseif ($result['status'] === 'taken')
                            <span class="text-sm text-base/50">Taken</span>
                        @else
                            <span class="text-sm text-base/50">Unavailable to check</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
