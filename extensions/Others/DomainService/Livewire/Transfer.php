<?php

namespace Paymenter\Extensions\Others\DomainService\Livewire;

use App\Classes\Cart;
use App\Exceptions\DisplayException;
use App\Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Paymenter\Extensions\Others\DomainService\Support\DomainAvailability;

class Transfer extends Component
{
    public string $domain = '';

    public string $authCode = '';

    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'regex:/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i'],
            'authCode' => ['required', 'string', 'max:255'],
        ];
    }

    public function submit(): mixed
    {
        if (!Auth::check()) {
            return redirect()->guest(route('login'));
        }

        $this->validate();

        $key = 'domaintransfer:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->notify('Too many attempts. Please wait a minute and try again.', 'error');

            return null;
        }
        RateLimiter::hit($key, 60);

        $name = strtolower(trim($this->domain));

        // A domain that is not registered anywhere cannot be transferred in —
        // steer the customer to registration instead. Only a definite
        // "available" blocks; unknown is allowed through.
        if ((new DomainAvailability)->check([$name])[$name] === DomainAvailability::AVAILABLE) {
            $this->addError('domain', 'That domain is not registered yet — you can register it instead of transferring.');

            return null;
        }

        $dot = strpos($name, '.');

        if ($dot === false) {
            $this->addError('domain', 'Enter a full domain name, for example example.com.');

            return null;
        }

        try {
            Cart::addDomain($name, substr($name, $dot + 1), 'transfer', 1, $this->authCode);
        } catch (DisplayException $e) {
            $this->notify($e->getMessage(), 'error');

            return null;
        }

        return $this->redirect(route('cart'), true);
    }

    public function render()
    {
        return view('domainservice::transfer')->layoutData(['title' => 'Transfer a domain']);
    }
}
