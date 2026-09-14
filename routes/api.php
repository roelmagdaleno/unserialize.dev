<?php

use App\Http\Controllers\Api\V1\UnserializeController;
use App\Http\Middleware\RequireJsonContentType;
use Illuminate\Support\Facades\Route;

Route::post('/v1/unserialize', UnserializeController::class)
    ->middleware([RequireJsonContentType::class, 'throttle:unserialize-api'])
    ->name('api.v1.unserialize');
