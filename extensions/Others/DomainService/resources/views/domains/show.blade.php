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

        @if ($domain->status === 'grace')
            <div class="mt-8 flex items-start gap-3 rounded-lg border border-warning/30 bg-warning/10 p-4">
                <x-ri-error-warning-fill class="size-5 text-warning shrink-0 mt-0.5" />
                <div>
                    <p class="font-semibold">This domain has expired</p>
                    <p class="text-sm text-base/60 mt-0.5">
                        It's still within its grace period — renew now at the normal price before it moves into
                        redemption, where a recovery fee applies.
                    </p>
                </div>
            </div>
        @elseif ($domain->status === 'redemption')
            <div class="mt-8 flex items-start gap-3 rounded-lg border border-error/30 bg-error/10 p-4">
                <x-ri-error-warning-fill class="size-5 text-error shrink-0 mt-0.5" />
                <div>
                    <p class="font-semibold">This domain is in redemption</p>
                    <p class="text-sm text-base/60 mt-0.5">
                        Its grace period has passed. It can still be recovered, but renewing now includes a
                        redemption fee. Past this window the domain is released and cannot be recovered here.
                    </p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8 items-start">
            <div class="flex flex-col gap-6 lg:col-span-2">

                <div class="card p-6">
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

                @if ($capabilities['dnsRecords'] ?? false)
                    <div class="card p-6">
                        <h2 class="text-xl font-semibold">DNS records</h2>
                        <p class="text-sm text-base/60 mt-1.5">Point subdomains, mail and other services without changing your nameservers.</p>

                        @if ($dnsRecords)
                            <div class="mt-5 overflow-x-auto">
                                <table class="w-full text-sm min-w-[480px]">
                                    <thead>
                                        <tr class="text-left text-base/50 border-b border-neutral">
                                            <th class="py-2 pr-4 font-medium">Type</th>
                                            <th class="py-2 pr-4 font-medium">Host</th>
                                            <th class="py-2 pr-4 font-medium">Value</th>
                                            <th class="py-2 pr-4 font-medium">TTL</th>
                                            <th class="py-2 pr-4"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($dnsRecords as $index => $record)
                                            <tr class="border-b border-neutral/60">
                                                <td class="py-2.5 pr-4 font-semibold">{{ $record['type'] }}</td>
                                                <td class="py-2.5 pr-4 break-all">{{ $record['host'] }}</td>
                                                <td class="py-2.5 pr-4 break-all text-base/70">{{ $record['value'] }}</td>
                                                <td class="py-2.5 pr-4 text-base/50">{{ $record['ttl'] }}s</td>
                                                <td class="py-2.5 pr-4 text-right">
                                                    <button type="button" wire:click="deleteDnsRecordAction({{ $index }})"
                                                        wire:confirm="Remove this DNS record?"
                                                        wire:loading.attr="disabled" wire:target="deleteDnsRecordAction({{ $index }})"
                                                        class="text-error hover:text-error/80">
                                                        <x-ri-delete-bin-line class="size-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <form wire:submit="addDnsRecordAction" class="mt-5 grid grid-cols-2 sm:grid-cols-5 gap-3 items-start">
                            <div class="col-span-1">
                                <label for="dns-type" class="sr-only">Type</label>
                                <select id="dns-type" wire:model="newDnsType"
                                    class="w-full bg-background-secondary border border-neutral rounded-md px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="A">A</option>
                                    <option value="AAAA">AAAA</option>
                                    <option value="CNAME">CNAME</option>
                                    <option value="MX">MX</option>
                                    <option value="TXT">TXT</option>
                                    <option value="NS">NS</option>
                                </select>
                            </div>
                            <div class="col-span-1">
                                <label for="dns-host" class="sr-only">Host</label>
                                <input id="dns-host" type="text" wire:model="newDnsHost" placeholder="www" autocomplete="off"
                                    class="w-full bg-background-secondary border rounded-md px-3 py-2.5 text-sm placeholder:text-base/30 focus:outline-none focus:ring-2 focus:ring-primary @error('newDnsHost') border-error @else border-neutral @enderror">
                                @error('newDnsHost') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-2 sm:col-span-2">
                                <label for="dns-value" class="sr-only">Value</label>
                                <input id="dns-value" type="text" wire:model="newDnsValue" placeholder="203.0.113.10" autocomplete="off"
                                    class="w-full bg-background-secondary border rounded-md px-3 py-2.5 text-sm placeholder:text-base/30 focus:outline-none focus:ring-2 focus:ring-primary @error('newDnsValue') border-error @else border-neutral @enderror">
                                @error('newDnsValue') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit"
                                class="col-span-2 sm:col-span-1 flex items-center gap-1.5 justify-center bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2.5 px-4 rounded-md duration-300 disabled:opacity-50"
                                wire:loading.attr="disabled" wire:target="addDnsRecordAction">
                                <x-ri-add-line class="size-4" />
                                <span wire:loading.remove wire:target="addDnsRecordAction">Add</span>
                                <span wire:loading wire:target="addDnsRecordAction">Adding…</span>
                            </button>
                        </form>
                    </div>
                @endif

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

                    <hr class="border-neutral my-5">

                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-medium text-sm">Auto-renew</p>
                            <p class="text-sm text-base/60 mt-0.5">We'll invoice you ahead of the renewal date.</p>
                        </div>
                        <button type="button" wire:click="toggleAutorenew" wire:loading.attr="disabled" wire:target="toggleAutorenew"
                            class="text-sm font-semibold whitespace-nowrap shrink-0 {{ $autorenew ? 'text-base/60 hover:text-base' : 'text-primary hover:text-primary/80' }}">
                            {{ $autorenew ? 'Turn off' : 'Turn on' }}
                        </button>
                    </div>
                    <p class="text-sm mt-1 {{ $autorenew ? 'text-success' : 'text-base/50' }}">{{ $autorenew ? 'On' : 'Off' }}</p>
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

                @if ($capabilities['eppRetrieval'] ?? false)
                    <div class="card p-6">
                        <h2 class="text-xl font-semibold flex items-center gap-2">
                            <x-ri-lock-password-fill class="size-5 text-base/60" /> Transfer code
                        </h2>
                        <p class="text-sm text-base/60 mt-1.5">Needed only if you're moving this domain to another registrar.</p>

                        @if ($eppCode)
                            <div class="mt-4 flex items-center justify-between gap-3 rounded-md border border-neutral bg-background px-4 py-3">
                                <code class="text-sm font-semibold break-all">{{ $eppCode }}</code>
                                <button type="button" wire:click="hideEppCode" class="text-base/50 hover:text-base shrink-0">
                                    <x-ri-close-line class="size-4" />
                                </button>
                            </div>
                            <p class="text-xs text-base/50 mt-2">Keep this private — anyone with it can request a transfer.</p>
                        @else
                            <button type="button" wire:click="getEppCode" wire:loading.attr="disabled" wire:target="getEppCode"
                                class="mt-4 text-sm font-semibold text-primary hover:text-primary/80">
                                <span wire:loading.remove wire:target="getEppCode">Reveal transfer code</span>
                                <span wire:loading wire:target="getEppCode">Fetching…</span>
                            </button>
                        @endif
                    </div>
                @endif

            </div>
        </div>

    @endif
</div>
