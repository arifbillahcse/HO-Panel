<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\DomainService\Livewire\Domains;
use Paymenter\Extensions\Others\DomainService\Livewire\Search;

// Searching is public; the register action inside handles authentication.
Route::group(['middleware' => ['web', 'checkout']], function () {
    Route::get('/domains', Search::class)->name('domainservice.search');
});

Route::group(['middleware' => ['web', 'auth']], function () {
    Route::get('/domains/manage', Domains\Index::class)->name('domainservice.domains');

    // Ownership is enforced in the component's mount(), so a guessed id 403s.
    Route::get('/domains/manage/{domain}', Domains\Show::class)->name('domainservice.domains.show');
});
