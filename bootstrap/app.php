<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /**
         * Tampered `/livewire/update` requests are client errors, not
         * application faults: they are never reported and answer 400.
         *
         * - Mapped rather than given a `render` callback, because two of these
         *   exceptions define their own `render()`, which the handler runs
         *   before any callback and which answers 419 outside debug mode.
         * - Also listed in `dontReport`, because `shouldReport()` checks the
         *   exception before it is mapped.
         */
        $tamperedLivewireRequests = [
            MethodNotFoundException::class,
            CannotUpdateLockedPropertyException::class,
            CorruptComponentPayloadException::class,
        ];

        $exceptions->dontReport($tamperedLivewireRequests);

        foreach ($tamperedLivewireRequests as $exception) {
            $exceptions->map($exception, fn (): HttpResponseException => new HttpResponseException(
                response()->json(['message' => 'Bad Request'], 400),
            ));
        }
    })->create();
