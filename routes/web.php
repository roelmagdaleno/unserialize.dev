<?php

use App\Http\Middleware\AddLegacyOutputNoIndexHeader;
use App\Livewire\Output;
use App\Livewire\Serialized;
use Illuminate\Support\Facades\Route;

Route::get('/', Serialized::class)->name('home');
Route::view('/privacy', 'privacy')->name('privacy');
Route::get('/o', fn () => redirect('/'));
Route::get('/o/{output}', Output::class)
    ->middleware(AddLegacyOutputNoIndexHeader::class)
    ->name('outputs')
    ->missing(fn () => response('Not Found', 404)->header('X-Robots-Tag', 'noindex, nofollow, noarchive'));
