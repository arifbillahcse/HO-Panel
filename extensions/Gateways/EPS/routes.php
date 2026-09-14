<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Gateways\EPS\EPS;

/*
 | The reference is a path segment rather than a query parameter: EPS appends
 | its own query string to whatever return URL it is given, and a customer can
 | come back inside a payment-app webview with no session cookie. Neither
 | 'auth' nor CSRF applies — nothing here is trusted without a verification
 | call to EPS first.
 */
Route::get('/extensions/gateways/eps/return/{reference}', [EPS::class, 'returned'])
    ->middleware('web')
    ->where('reference', '[A-Za-z0-9\-]+')
    ->name('extensions.gateways.eps.return');
