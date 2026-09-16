<?php

use App\Enums\ConversionErrorCode;
use App\Services\Serialized;

/**
 * The input limit is enforced in one place and advertised in a dozen.
 *
 * `public/openapi.json`, the Blade pages and `llms.txt` are static artifacts:
 * nothing renders them from the constant, so nothing stopped them drifting away
 * from it. These tests are the guard that makes a drift fail here rather than
 * leave a published document quietly lying about what the API accepts. The
 * dataset name identifies the offending file when one of them fails.
 */
$published = [
    'public/openapi.json',
    'resources/llms.txt',
    'resources/views/developers.blade.php',
    'resources/views/markdown/developers.blade.php',
    'resources/views/privacy.blade.php',
    'resources/views/markdown/privacy.blade.php',
    'resources/views/markdown/home.blade.php',
    'resources/views/livewire/serialized.blade.php',
];

it('advertises the real input limit in every published artifact', function (string $path) {
    $contents = file_get_contents(base_path($path));

    expect($contents)->toContain(number_format(Serialized::MAX_INPUT_BYTES));
})->with($published);

it('mentions no byte count other than the real input limit', function (string $path) {
    $contents = file_get_contents(base_path($path));

    /**
     * Any other byte-sized number in these files is a stale limit. Both the
     * grouped spelling and the plain one are allowed, so only a third, wrong
     * figure trips this.
     */
    preg_match_all('/\b\d{1,3}(?:,\d{3})+\b|\b\d{6,}\b/', $contents, $matches);

    $stale = array_diff($matches[0], [
        number_format(Serialized::MAX_INPUT_BYTES),
        (string) Serialized::MAX_INPUT_BYTES,
    ]);

    expect(array_values(array_unique($stale)))->toBe([]);
})->with($published);

it('derives the too-large message from the constant that enforces it', function () {
    expect(ConversionErrorCode::InputTooLarge->message())
        ->toContain(number_format(Serialized::MAX_INPUT_BYTES));
});
