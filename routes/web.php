<?php

use App\Http\Middleware\AddLegacyOutputNoIndexHeader;
use App\Livewire\Output;
use App\Livewire\Serialized;
use Illuminate\Support\Facades\Route;

Route::get('/', Serialized::class)->name('home');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/guides/php-serialization', 'guides.serialization')->name('guides.serialization');
Route::view('/guides/wordpress', 'guides.wordpress')->name('guides.wordpress');
Route::view('/security', 'security')->name('security');
Route::get('/sitemap.xml', function () {
    return response()
        ->view('sitemap')
        ->header('Content-Type', 'application/xml');
})->name('sitemap');
Route::get('/robots.txt', fn () => response("User-agent: *\nAllow: /\n\nSitemap: https://unserialize.dev/sitemap.xml\n", 200, [
    'Content-Type' => 'text/plain',
]));
Route::get('/openapi.json', fn () => response(
    file_get_contents(public_path('openapi.json')),
    200,
    ['Content-Type' => 'application/json'],
))->name('openapi');
Route::get('/o', fn () => redirect('/'));
Route::get('/o/{output}', Output::class)
    ->middleware(AddLegacyOutputNoIndexHeader::class)
    ->name('outputs')
    ->missing(fn () => response('Not Found', 404)->header('X-Robots-Tag', 'noindex, nofollow, noarchive'));
