<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Servers\Cosmotown\Livewire\Domains;
use Paymenter\Extensions\Servers\Cosmotown\Livewire\DomainSearch;

// Public: searching for a domain should not require an account. The checkout
// this links into handles authentication itself.
Route::group(['middleware' => ['web', 'checkout']], function () {
    Route::get('/domains', DomainSearch::class)->name('cosmotown.search');
});

Route::group(['middleware' => ['web', 'auth']], function () {
    Route::get('/domains/manage', Domains\Index::class)
        ->name('cosmotown.domains');

    // can:view,service reuses ServicePolicy, so one customer cannot open
    // another's domain by guessing the id.
    Route::get('/domains/manage/{service}', Domains\Show::class)
        ->name('cosmotown.domains.show')
        ->middleware('can:view,service');
});
