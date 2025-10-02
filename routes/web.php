<?php

use App\Livewire\Output;
use App\Livewire\Serialized;
use Illuminate\Support\Facades\Route;

Route::get('/', Serialized::class)->name('home');
Route::get('/o', fn () => redirect('/'));
Route::get('/o/{output}', Output::class)->name('outputs');
