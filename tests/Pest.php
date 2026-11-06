<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\ConverterResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/**
 * Submit one value to the converter the way its page does: as the raw body.
 */
function convertInBrowser(string $serializedData): ConverterResponse
{
    return new ConverterResponse(test()->call(
        'POST',
        route('conversion.convert'),
        server: ['CONTENT_TYPE' => 'text/plain;charset=UTF-8', 'HTTP_ACCEPT' => 'application/json'],
        content: $serializedData,
    ));
}
