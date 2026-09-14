<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Servers\Cosmotown\Livewire\DomainSearch;

// Public: searching for a domain should not require an account. The checkout
// this links into handles authentication itself.
Route::group(['middleware' => ['web', 'checkout']], function () {
    Route::get('/domains', DomainSearch::class)->name('cosmotown.search');
});
