@php
    $user = Auth::user();

    $activeServices = $user->services()
        ->where('status', 'active')
        ->with(['product.category', 'plan', 'properties'])
        ->get();

    $pendingInvoices = $user->invoices()->where('status', 'pending')->count();
    $openTickets = config('settings.tickets_disabled', false)
        ? 0
        : $user->tickets()->where('status', '!=', 'closed')->count();
    $serviceCount = $user->services()->where('status', '!=', 'cancelled')->count();

    $creditsEnabled = config('settings.credits_enabled', false);
    $credit = $creditsEnabled ? $user->credits()->first() : null;
@endphp

<div class="container mt-14">
    <x-navigation.breadcrumb />

    <h1 class="text-3xl font-bold mt-2">{{ __('dashboard.welcome_back', ['name' => $user->first_name]) }}</h1>
    <p class="text-base/60 mt-2">{{ __('dashboard.dashboard_description') }}</p>

    {{-- Billing alert. Only rendered when there is actually something to pay,
         so the dashboard stays quiet for customers in good standing. --}}
    @if ($pendingInvoices > 0)
        <a href="{{ route('invoices') }}" wire:navigate
            class="mt-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-lg border border-error/30 bg-error/10 p-4 hover:bg-error/15 duration-200">
            <div class="flex items-start gap-3">
                <x-ri-error-warning-fill class="size-5 text-error shrink-0 mt-0.5" />
                <div>
                    <p class="font-semibold text-base">
                        {{ trans_choice('You have :count unpaid invoice|You have :count unpaid invoices', $pendingInvoices, ['count' => $pendingInvoices]) }}
                    </p>
                    <p class="text-sm text-base/60 mt-0.5">Settle it to keep your services running without interruption.</p>
                </div>
            </div>
            <span class="flex items-center gap-2 justify-center bg-error text-white text-sm font-semibold hover:bg-error/80 py-2 px-4.5 rounded-md shrink-0 duration-300">
                Pay now
                <x-ri-arrow-right-fill class="size-4" />
            </span>
        </a>
    @endif

    {{-- Hero: the services themselves, not a summary of them. --}}
    <section class="mt-10">
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
                <div class="bg-background-secondary border border-neutral p-2 rounded-lg">
                    <x-ri-archive-stack-fill class="size-5" />
                </div>
                <h2 class="text-xl font-semibold">{{ __('dashboard.active_services') }}</h2>
            </div>
            @if ($activeServices->isNotEmpty())
                <a href="{{ route('services') }}" wire:navigate
                    class="text-sm font-semibold text-primary hover:text-primary/80 flex items-center gap-1">
                    {{ __('dashboard.view_all') }}
                    <x-ri-arrow-right-long-line class="size-4" />
                </a>
            @endif
        </div>

        @forelse ($activeServices as $service)
            @php
                $domain = $service->properties->firstWhere('key', 'domain')?->value;
                $panelUser = $service->properties->firstWhere('key', 'cpanel_username')?->value;
            @endphp

            <div class="card p-6 mb-4">
                <div class="flex flex-col lg:flex-row lg:items-center gap-6 lg:gap-8">

                    <div class="flex-grow min-w-0">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-success/15 text-success text-xs font-semibold px-2.5 py-1">
                                <x-ri-checkbox-circle-fill class="size-3.5" />
                                Active
                            </span>
                            <h3 class="text-lg font-semibold truncate">{{ $service->label }}</h3>
                        </div>

                        @if ($domain)
                            <p class="text-primary font-medium mt-2 truncate">{{ $domain }}</p>
                        @endif

                        <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 mt-3 text-sm text-base/60">
                            <span>{{ $service->product->category->name }}</span>

                            @if (in_array($service->plan->type, ['recurring']))
                                <span class="flex items-center gap-2">
                                    <x-ri-circle-fill class="size-1 text-base/20" />
                                    {{ __('services.every_period', [
                                        'period' => $service->plan->billing_period > 1 ? $service->plan->billing_period : '',
                                        'unit' => trans_choice(__('services.billing_cycles.' . $service->plan->billing_unit), $service->plan->billing_period),
                                    ]) }}
                                </span>
                            @endif

                            @if ($panelUser)
                                <span class="flex items-center gap-2">
                                    <x-ri-circle-fill class="size-1 text-base/20" />
                                    <x-ri-computer-line class="size-4" />
                                    {{ $panelUser }}
                                </span>
                            @endif
                        </div>

                        @if ($service->expires_at)
                            <p class="text-sm text-base/60 mt-3">
                                {{ __('services.renews_on') }}
                                <span class="text-base font-medium">{{ $service->expires_at->format('M d, Y') }}</span>
                                <span class="text-base/40">({{ $service->expires_at->diffForHumans() }})</span>
                            </p>
                        @endif
                    </div>

                    {{-- The control panel lives one click away on the service page,
                         where the server module can mint a fresh SSO session. --}}
                    <div class="flex flex-col sm:flex-row lg:flex-col items-stretch gap-3 lg:w-56 shrink-0">
                        <span class="text-2xl font-bold lg:text-right whitespace-nowrap">{{ $service->formattedPrice }}</span>
                        <a href="{{ route('services.show', $service) }}" wire:navigate class="w-full">
                            <span class="flex items-center gap-2 justify-center bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2.5 px-4.5 rounded-md w-full duration-300">
                                <x-ri-computer-line class="size-4" />
                                Manage &amp; open panel
                            </span>
                        </a>
                    </div>

                </div>
            </div>
        @empty
            <div class="card p-10 text-center">
                <div class="bg-background border border-neutral p-3 rounded-lg w-fit mx-auto">
                    <x-ri-shopping-bag-4-fill class="size-6 text-base/40" />
                </div>
                <h3 class="text-lg font-semibold mt-4">No active services yet</h3>
                <p class="text-sm text-base/60 mt-1.5 max-w-sm mx-auto">Once you order hosting it will appear here, with a direct link into your control panel.</p>
                <a href="{{ route('home') }}" wire:navigate class="inline-block mt-5">
                    <span class="flex items-center gap-2 justify-center bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2.5 px-4.5 rounded-md duration-300">
                        Browse hosting plans
                        <x-ri-arrow-right-fill class="size-4" />
                    </span>
                </a>
            </div>
        @endforelse
    </section>

    {{-- Counts compressed to a thin strip: reference, not headline. --}}
    <section class="mt-8 grid grid-cols-2 {{ $creditsEnabled ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }} gap-px bg-neutral border border-neutral rounded-lg overflow-hidden">
        <a href="{{ route('services') }}" wire:navigate class="bg-background-secondary hover:bg-background duration-200 px-5 py-4">
            <span class="flex items-center gap-2 text-sm text-base/60">
                <x-ri-archive-stack-fill class="size-4" />
                Services
            </span>
            <span class="block text-2xl font-bold mt-1 tabular-nums">{{ $serviceCount }}</span>
        </a>

        <a href="{{ route('invoices') }}" wire:navigate class="bg-background-secondary hover:bg-background duration-200 px-5 py-4">
            <span class="flex items-center gap-2 text-sm text-base/60">
                <x-ri-receipt-fill class="size-4" />
                Unpaid
            </span>
            <span class="block text-2xl font-bold mt-1 tabular-nums {{ $pendingInvoices > 0 ? 'text-error' : '' }}">{{ $pendingInvoices }}</span>
        </a>

        @if (!config('settings.tickets_disabled', false))
            <a href="{{ route('tickets') }}" wire:navigate class="bg-background-secondary hover:bg-background duration-200 px-5 py-4">
                <span class="flex items-center gap-2 text-sm text-base/60">
                    <x-ri-customer-service-fill class="size-4" />
                    Open tickets
                </span>
                <span class="block text-2xl font-bold mt-1 tabular-nums">{{ $openTickets }}</span>
            </a>
        @endif

        @if ($creditsEnabled)
            <a href="{{ route('account.credits') }}" wire:navigate class="bg-background-secondary hover:bg-background duration-200 px-5 py-4">
                <span class="flex items-center gap-2 text-sm text-base/60">
                    <x-ri-copper-coin-line class="size-4" />
                    Credit
                </span>
                <span class="block text-2xl font-bold mt-1 tabular-nums">{{ $credit?->formattedAmount ?? '—' }}</span>
            </a>
        @endif
    </section>

    {{-- Detail below the fold. --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 mt-12 items-start">
        <div>
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3">
                    <div class="bg-background-secondary border border-neutral p-2 rounded-lg">
                        <x-ri-receipt-fill class="size-5" />
                    </div>
                    <h2 class="text-xl font-semibold">{{ __('dashboard.unpaid_invoices') }}</h2>
                </div>
                <a href="{{ route('invoices') }}" wire:navigate
                    class="text-sm font-semibold text-primary hover:text-primary/80 flex items-center gap-1">
                    {{ __('dashboard.view_all') }}
                    <x-ri-arrow-right-long-line class="size-4" />
                </a>
            </div>
            <livewire:invoices.widget :limit="3" />
        </div>

        @if (!config('settings.tickets_disabled', false))
            <div>
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <div class="bg-background-secondary border border-neutral p-2 rounded-lg">
                            <x-ri-customer-service-fill class="size-5" />
                        </div>
                        <h2 class="text-xl font-semibold">{{ __('dashboard.open_tickets') }}</h2>
                        <a href="{{ route('tickets.create') }}" wire:navigate aria-label="Open a new ticket">
                            <x-ri-add-fill class="size-5" />
                        </a>
                    </div>
                    <a href="{{ route('tickets') }}" wire:navigate
                        class="text-sm font-semibold text-primary hover:text-primary/80 flex items-center gap-1">
                        {{ __('dashboard.view_all') }}
                        <x-ri-arrow-right-long-line class="size-4" />
                    </a>
                </div>
                <livewire:tickets.widget />
            </div>
        @endif
    </div>

    {!! hook('pages.dashboard') !!}
</div>
