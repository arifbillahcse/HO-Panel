@php
    // The core component paginates without eager loading properties, so load
    // them once for the page rather than letting the loop below issue a query
    // per service.
    $services->getCollection()->loadMissing('properties');

    // A hosting service stores whatever the customer typed at checkout as a
    // property, keyed by the config option's env variable (or its name when
    // there isn't one) — see Cart::checkout(). Domains registered through the
    // Cosmotown extension use the same 'domain' key, so one lookup covers both.
    $hostname = function ($service) {
        foreach (['domain', 'hostname', 'Domain', 'Hostname'] as $key) {
            $value = $service->properties->firstWhere('key', $key)?->value;

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    };
@endphp

<div class="container mt-14 space-y-4">
    <x-navigation.breadcrumb />
    @forelse ($services as $service)
    <a href="{{ route('services.show', $service) }}" wire:navigate>
        <div class="bg-background-secondary hover:bg-background-secondary/80 border border-neutral p-4 rounded-lg mb-4">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-3 min-w-0">
            <div class="bg-secondary/10 p-2 rounded-lg shrink-0">
                <x-ri-instance-line class="size-5 text-secondary" />
            </div>
            <div class="min-w-0">
                @if ($domain = $hostname($service))
                    {{-- Lead with the domain. It is what the customer recognises;
                         the plan name is the supporting detail, not the headline. --}}
                    <span class="font-medium block truncate">{{ $domain }}</span>
                    <span class="text-sm text-base/60 block truncate">{{ $service->label }}</span>
                @else
                    <span class="font-medium block truncate">{{ $service->label }}</span>
                @endif
            </div>
            </div>
            <div class="size-5 rounded-md p-0.5 shrink-0
                @if ($service->status == 'active') text-success bg-success/20 
                @elseif($service->status == 'suspended' || $service->status == 'cancelled') text-inactive bg-inactive/20
                @else text-warning bg-warning/20 
                @endif">
                @if ($service->status == 'active')
                    <x-ri-checkbox-circle-fill />
                @elseif($service->status == 'suspended' || $service->status == 'cancelled')
                    <x-ri-forbid-fill />
                @elseif($service->status == 'pending')
                    <x-ri-error-warning-fill />
                @endif
            </div>
        </div>
        <div class="text-base text-sm flex gap-1">
            {{
                in_array($service->plan->type, ['recurring']) ?  __('services.every_period', [
                'period' => $service->plan->billing_period > 1 ? $service->plan->billing_period : '',
                'unit' => trans_choice(__('services.billing_cycles.' . $service->plan->billing_unit),
                $service->plan->billing_period)
                ]) : '' }}
                @if($service->expires_at && $service->expires_at > now())
                -  {{ __('services.renews_in') }} 
                <x-tooltip :message="$service->expires_at->format('M d, Y')">
                    {{ $service->expires_at->longAbsoluteDiffForHumans() }}
                </x-tooltip>
                @endif
            </div>
        </div>
    </a>
    @empty
    <div class="bg-background-secondary border border-neutral p-4 rounded-lg">
        <p class="text-base text-sm">{{ __('services.no_services') }}</p>
    </div>
    @endforelse

    {{ $services->links() }}
</div>
