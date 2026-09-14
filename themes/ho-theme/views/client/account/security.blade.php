@php
    /*
     | ho-theme override of the client Security page.
     |
     | Presentation only. Every action here calls a method that already exists
     | on App\Livewire\Client\Security — logoutSession, changePassword,
     | enableTwoFactor, disableTwoFactor — so no core code is touched and the
     | page keeps working across Paymenter updates. Data the panel does not
     | store (per-session OTP state, a parsed browser version in the auth log)
     | is deliberately not invented.
     */
    $user = Auth::user();

    $sessions = $user->sessions()->orderByDesc('last_activity')->get();
    $logs = $user->authenticationLogs()->orderByDesc('last_used_at')->limit(10)->get();

    $signInsRecorded = $user->authenticationLogs()->count();
    $mostRecent = $sessions->max('last_activity');
    $twoFactor = (bool) $user->tfa_secret;

    // A friendlier device string than the model's "Windows - Chrome": browser
    // first, then OS, plus a desktop/mobile hint — the shape people recognise.
    $device = function (?string $ua) {
        $ua = (string) $ua;

        $browser = match (true) {
            (bool) preg_match('/Edg\//i', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $ua) => 'Opera',
            (bool) preg_match('/Firefox/i', $ua) => 'Firefox',
            (bool) preg_match('/Chrome/i', $ua) => 'Chrome',
            (bool) preg_match('/Safari/i', $ua) => 'Safari',
            (bool) preg_match('/MSIE|Trident/i', $ua) => 'Internet Explorer',
            default => 'Unknown browser',
        };

        $os = match (true) {
            (bool) preg_match('/Windows NT 10/i', $ua) => 'Windows 10 / 11',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/iPhone|iPad|iOS/i', $ua) => 'iOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Mac OS X|Macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            default => 'Unknown OS',
        };

        $mobile = (bool) preg_match('/Mobile|Android|iPhone|iPad/i', $ua);

        return [
            'browser' => $browser,
            'os' => $os,
            'type' => $mobile ? 'Mobile' : 'Desktop',
            'mobile' => $mobile,
        ];
    };
@endphp

<div class="container mt-14">
    <x-navigation.breadcrumb />

    <div class="px-2 flex flex-col gap-6">

        {{-- ============================ SESSIONS ============================ --}}
        <div class="card p-6">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">Where your account is signed in</h1>
                    <p class="text-sm text-base/60 mt-1.5 max-w-xl">
                        Every active sign-in to your account is listed below, with the device, browser
                        and IP address it came from. If you see something you do not recognise, sign it
                        out and change your password.
                    </p>
                </div>
                <span class="shrink-0 inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold
                    {{ $twoFactor ? 'bg-success/15 text-success' : 'bg-warning/15 text-warning' }}">
                    <x-ri-secure-payment-line class="size-4" />
                    {{ $twoFactor ? 'Two-factor on' : 'Two-factor off' }}
                </span>
            </div>

            {{-- Stat row --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-6">
                <div class="rounded-lg border border-neutral bg-background p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-base/50">Active now</p>
                    <p class="text-2xl font-bold mt-1">{{ $sessions->count() }}</p>
                    <p class="text-xs text-base/50 mt-0.5">{{ \Illuminate\Support\Str::plural('device', $sessions->count()) }} signed in</p>
                </div>
                <div class="rounded-lg border border-neutral bg-background p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-base/50">Sign-ins recorded</p>
                    <p class="text-2xl font-bold mt-1">{{ $signInsRecorded }}</p>
                    <p class="text-xs text-base/50 mt-0.5">across all devices</p>
                </div>
                <div class="rounded-lg border border-neutral bg-background p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-base/50">Most recent activity</p>
                    <p class="text-lg font-bold mt-1">{{ $mostRecent ? $mostRecent->format('M d, Y') : '—' }}</p>
                    <p class="text-xs text-base/50 mt-0.5">{{ $mostRecent ? $mostRecent->format('h:i A') : 'no active sessions' }}</p>
                </div>
            </div>

            {{-- Active sessions --}}
            <div class="mt-6 flex flex-col gap-3">
                @foreach ($sessions as $session)
                    @php $d = $device($session->user_agent); @endphp
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-lg border p-4
                        {{ $session->is_current_device ? 'border-primary/40 bg-primary/5' : 'border-neutral bg-background' }}">

                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="shrink-0 rounded-lg border border-neutral bg-background-secondary p-2.5">
                                @if ($d['mobile'])
                                    <x-ri-instance-line class="size-5 text-base/60" />
                                @else
                                    <x-ri-computer-line class="size-5 text-base/60" />
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="font-semibold truncate">{{ $d['browser'] }} on {{ $d['os'] }}</p>
                                    @if ($session->is_current_device)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-success/15 text-success text-xs font-semibold px-2 py-0.5">
                                            <x-ri-checkbox-circle-fill class="size-3.5" />
                                            This device
                                        </span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-sm text-base/60">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-ri-global-line class="size-3.5" />
                                        {{ $session->ip_address ?? 'unknown IP' }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5">
                                        Active {{ $session->last_activity?->diffForHumans() ?? 'unknown' }}
                                    </span>
                                    <span class="text-base/40">{{ $d['type'] }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0">
                            @if ($session->is_current_device)
                                <span class="text-sm text-base/40 italic">Current session</span>
                            @else
                                <button type="button"
                                    wire:click="logoutSession('{{ $session->id }}')"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-error/40 text-error text-sm font-semibold px-4 py-2 hover:bg-error/10 duration-200">
                                    <x-ri-close-line class="size-4" />
                                    Sign out
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Sign-in history: the auth log stores IP + time only, no device --}}
            @if ($logs->isNotEmpty())
                <div class="mt-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-base/50 flex items-center gap-2">
                        <x-ri-newspaper-line class="size-4" />
                        Recent sign-in history
                    </h2>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-sm min-w-[360px]">
                            <thead>
                                <tr class="text-left text-base/50 border-b border-neutral">
                                    <th class="py-2 pr-4 font-medium">IP address</th>
                                    <th class="py-2 pr-4 font-medium">When</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logs as $log)
                                    <tr class="border-b border-neutral/60">
                                        <td class="py-2.5 pr-4 font-medium">{{ $log->ip_address }}</td>
                                        <td class="py-2.5 pr-4 text-base/60">
                                            @php
                                                // last_used_at is not cast on the model, so normalise it
                                                // rather than calling ->format() on a raw string.
                                                $when = $log->last_used_at ?? $log->created_at;
                                                $when = $when ? \Illuminate\Support\Carbon::parse($when) : null;
                                            @endphp
                                            {{ $when ? $when->format('M d, Y · h:i A') : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- ========================= CHANGE PASSWORD ========================= --}}
        <div class="card p-6">
            <h2 class="text-lg font-bold flex items-center gap-2">
                <x-ri-lock-password-fill class="size-5 text-base/60" />
                {{ __('account.change_password') }}
            </h2>
            <p class="text-sm text-base/60 mt-1">Changing your password signs out every other device automatically.</p>

            <form wire:submit="changePassword" class="mt-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-form.input divClass="sm:col-span-2" name="current_password" type="password"
                        :label="__('account.input.current_password')"
                        :placeholder="__('account.input.current_password_placeholder')" wire:model="current_password"
                        required />
                    <x-form.input name="password" type="password" :label="__('account.input.new_password')"
                        :placeholder="__('account.input.new_password_placeholder')" wire:model="password" required />
                    <x-form.input name="password_confirmation" type="password" :label="__('account.input.confirm_password')"
                        :placeholder="__('account.input.confirm_password_placeholder')" wire:model="password_confirmation"
                        required />
                </div>
                <x-button.primary type="submit" class="w-full mt-4">
                    {{ __('account.change_password') }}
                </x-button.primary>
            </form>
        </div>

        {{-- ===================== TWO-FACTOR AUTHENTICATION ==================== --}}
        <div class="card p-6">
            <h2 class="text-lg font-bold flex items-center gap-2">
                <x-ri-secure-payment-line class="size-5 text-base/60" />
                {{ __('account.two_factor_authentication') }}
            </h2>

            @if ($twoFactorEnabled)
                <p class="text-sm text-success mt-2 flex items-center gap-1.5">
                    <x-ri-checkbox-circle-fill class="size-4" />
                    {{ __('account.two_factor_authentication_enabled') }}
                </p>
                <x-button.primary class="w-full mt-4" x-on:click="$store.confirmation.confirm({
                        title: '{{ __('account.two_factor_authentication_disable') }}',
                        message: '{{ __('account.two_factor_authentication_disable_description') }}',
                        confirmText: '{{ __('account.confirm') }}',
                        cancelText: '{{ __('account.cancel') }}',
                        callback: () => $wire.disableTwoFactor()
                    })">
                    {{ __('account.two_factor_authentication_disable') }}
                </x-button.primary>
            @else
                <p class="text-sm text-base/60 mt-2">{{ __('account.two_factor_authentication_description') }}</p>
                <x-button.primary wire:click="enableTwoFactor" class="w-full mt-4">
                    {{ __('account.two_factor_authentication_enable') }}
                </x-button.primary>

                @if ($showEnableTwoFactor)
                    <x-modal :title="__('account.two_factor_authentication_enable')" open="true">
                        <p class="text-base/70">{{ __('account.two_factor_authentication_enable_description') }}</p>
                        <div class="flex flex-col items-center mt-4">
                            <img src="{{ $twoFactorData['image'] }}" alt="QR code" class="w-64 h-64" />
                            <p class="text-base/50 mt-2 text-sm text-center">
                                {{ __('account.two_factor_authentication_secret') }}<br />{{ $twoFactorData['secret'] }}
                            </p>
                        </div>
                        <form wire:submit.prevent="enableTwoFactor">
                            <x-form.input divClass="mt-8" name="two_factor_code" type="text"
                                :label="__('account.input.two_factor_code')"
                                :placeholder="__('account.input.two_factor_code_placeholder')" wire:model="twoFactorCode"
                                required />
                            <x-button.primary class="w-full mt-4" type="submit">
                                {{ __('account.two_factor_authentication_enable') }}
                            </x-button.primary>
                        </form>
                        <x-slot name="closeTrigger">
                            <button @click="document.location.reload()" class="text-base/60">
                                <x-ri-close-fill class="size-6" />
                            </button>
                        </x-slot>
                    </x-modal>
                @endif
            @endif
        </div>

    </div>
</div>
