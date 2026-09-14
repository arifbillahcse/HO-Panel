<div class="container mt-14">
    <div class="max-w-xl mx-auto">
        <a href="{{ route('domainservice.search') }}" wire:navigate
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:text-primary/80">
            <x-ri-arrow-right-fill class="size-4 rotate-180" /> Register instead
        </a>

        <h1 class="text-3xl font-bold mt-3">Transfer a domain to us</h1>
        <p class="text-base/60 mt-2">
            Move a domain you already own to Hostorio. Transfers usually take 5 to 7 days and include a one-year extension.
        </p>

        <form wire:submit="submit" class="mt-6 card p-6 flex flex-col gap-5">
            <div>
                <label for="domain" class="block text-sm font-semibold mb-1.5">Domain name</label>
                <input id="domain" type="text" wire:model="domain" autocomplete="off" spellcheck="false"
                    placeholder="example.com"
                    class="w-full bg-background-secondary border rounded-md px-4 py-2.5 text-sm placeholder:text-base/30 focus:outline-none focus:ring-2 focus:ring-primary @error('domain') border-error @else border-neutral @enderror">
                @error('domain') <p class="text-sm text-error mt-1.5">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="authCode" class="block text-sm font-semibold mb-1.5">Authorisation code (EPP)</label>
                <input id="authCode" type="text" wire:model="authCode" autocomplete="off" spellcheck="false"
                    placeholder="ABC123-xyz"
                    class="w-full bg-background-secondary border rounded-md px-4 py-2.5 text-sm placeholder:text-base/30 focus:outline-none focus:ring-2 focus:ring-primary @error('authCode') border-error @else border-neutral @enderror">
                @error('authCode') <p class="text-sm text-error mt-1.5">{{ $message }}</p> @enderror
                <p class="text-sm text-base/50 mt-1.5">
                    From your current registrar. Unlock the domain and turn WHOIS privacy off there first, and make sure it is more than 60 days old.
                </p>
            </div>

            <button type="submit"
                class="flex items-center gap-2 justify-center bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2.5 px-6 rounded-md duration-300 disabled:opacity-50"
                wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">Start transfer</span>
                <span wire:loading wire:target="submit">Starting…</span>
            </button>
        </form>
    </div>
</div>
