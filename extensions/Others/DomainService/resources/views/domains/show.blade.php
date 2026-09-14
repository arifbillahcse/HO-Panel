<div class="container mt-14">
    <a href="{{ route('domainservice.domains') }}" wire:navigate
        class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:text-primary/80">
        <x-ri-arrow-right-fill class="size-4 rotate-180" /> All domains
    </a>

    <h1 class="text-3xl font-bold mt-3 break-all">{{ $domain->name }}</h1>

    @if ($unreachable)
        <div class="mt-8 flex items-start gap-3 rounded-lg border border-error/30 bg-error/10 p-4">
            <x-ri-error-warning-fill class="size-5 text-error shrink-0 mt-0.5" />
            <div>
                <p class="font-semibold">We can't reach the registrar right now</p>
                <p class="text-sm text-base/60 mt-0.5">Your domain is unaffected. Try again in a few minutes.</p>
            </div>
        </div>
    @elseif ($domain->status === 'transfer_pending')
        <div class="mt-8 flex items-start gap-3 rounded-lg border border-warning/30 bg-warning/10 p-4">
            <x-ri-error-warning-fill class="size-5 text-warning shrink-0 mt-0.5" />
            <div>
                <p class="font-semibold">Transfer in progress</p>
                <p class="text-sm text-base/60 mt-0.5">
                    Transfers usually take 5 to 7 days. Your current registrar may email you to approve it —
                    approving speeds things up. Management options appear here once it lands.
                </p>
            </div>
        </div>
    @elseif ($domain->status === 'transfer_failed')
        <div class="mt-8 flex items-start gap-3 rounded-lg border border-error/30 bg-error/10 p-4">
            <x-ri-error-warning-fill class="size-5 text-error shrink-0 mt-0.5" />
            <div>
                <p class="font-semibold">Transfer could not be completed</p>
                <p class="text-sm text-base/60 mt-0.5">
                    We've been notified and will contact you. This usually means the domain is locked at your current
                    registrar, the authorisation code was wrong, or it was registered within the last 60 days.
                </p>
            </div>
        </div>
    @elseif (!$manageable)
        <div class="mt-8 flex items-start gap-3 rounded-lg border border-warning/30 bg-warning/10 p-4">
            <x-ri-error-warning-fill class="size-5 text-warning shrink-0 mt-0.5" />
            <div>
                <p class="font-semibold">This domain isn't ready to manage yet</p>
                <p class="text-sm text-base/60 mt-0.5">Management options appear once registration completes.</p>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8 items-start">
            <div class="card p-6 lg:col-span-2">
                <h2 class="text-xl font-semibold">Nameservers</h2>
                <p class="text-sm text-base/60 mt-1.5">These decide which servers answer for your domain. Enter at least two.</p>

                <form wire:submit="saveNameservers" class="mt-5 flex flex-col gap-3">
                    @foreach ($nameservers as $index => $nameserver)
                        <div>
                            <label for="ns-{{ $index }}" class="sr-only">Nameserver {{ $index + 1 }}</label>
                            <input id="ns-{{ $index }}" type="text" wire:model="nameservers.{{ $index }}"
                                placeholder="ns{{ $index + 1 }}.example.com" autocomplete="off" spellcheck="false"
                                class="w-full bg-background-secondary border rounded-md px-4 py-2.5 text-sm placeholder:text-base/30 focus:outline-none focus:ring-2 focus:ring-primary @error('nameservers.' . $index) border-error @else border-neutral @enderror">
                            @error('nameservers.' . $index)
                                <p class="text-sm text-error mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    <button type="submit"
                        class="flex items-center gap-2 justify-center bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2.5 px-5 rounded-md w-fit duration-300 disabled:opacity-50"
                        wire:loading.attr="disabled" wire:target="saveNameservers">
                        <span wire:loading.remove wire:target="saveNameservers">Save nameservers</span>
                        <span wire:loading wire:target="saveNameservers">Saving…</span>
                    </button>
                </form>
            </div>

            <div class="flex flex-col gap-6">
                <div class="card p-6">
                    <h2 class="text-xl font-semibold">Details</h2>
                    <dl class="mt-4 flex flex-col gap-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-base/60">Renews</dt>
                            <dd class="font-medium text-right">{{ $domain->expires_at?->format('M d, Y') ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-base/60">Status</dt>
                            <dd class="font-medium text-right">{{ str($domain->status)->headline() }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="card p-6">
                    <h2 class="text-xl font-semibold">Protection</h2>

                    <div class="mt-4 flex items-start justify-between gap-4">
                        <div>
                            <p class="font-medium text-sm">Registrar lock</p>
                            <p class="text-sm text-base/60 mt-0.5">Blocks transfers away from us.</p>
                        </div>
                        <button type="button" wire:click="toggleLock" wire:loading.attr="disabled" wire:target="toggleLock"
                            class="text-sm font-semibold whitespace-nowrap shrink-0 {{ $locked ? 'text-base/60 hover:text-base' : 'text-primary hover:text-primary/80' }}">
                            {{ $locked ? 'Unlock' : 'Lock' }}
                        </button>
                    </div>
                    <p class="text-sm mt-1 {{ $locked ? 'text-success' : 'text-base/50' }}">{{ $locked ? 'Locked' : 'Unlocked' }}</p>

                    <hr class="border-neutral my-5">

                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-medium text-sm">WHOIS privacy</p>
                            <p class="text-sm text-base/60 mt-0.5">Hides your details from public lookups.</p>
                        </div>
                        <button type="button" wire:click="togglePrivacy" wire:loading.attr="disabled" wire:target="togglePrivacy"
                            class="text-sm font-semibold whitespace-nowrap shrink-0 {{ $privacy ? 'text-base/60 hover:text-base' : 'text-primary hover:text-primary/80' }}">
                            {{ $privacy ? 'Disable' : 'Enable' }}
                        </button>
                    </div>
                    <p class="text-sm mt-1 {{ $privacy ? 'text-success' : 'text-base/50' }}">{{ $privacy ? 'On' : 'Off' }}</p>
                </div>
            </div>
        </div>
    @endif
</div>
