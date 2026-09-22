<?php

use App\Services\Serialized as Converter;
use Serialized\Serialized as Package;

/**
 * Keeps this application's JSON identical to the package's own.
 *
 * {@see Converter::convert()} decodes once and encodes the value itself rather than
 * asking the package for JSON a second time, which would re-read and re-validate the
 * whole payload. That shortcut is only safe while the package's normalization has
 * nothing to do, which holds because no class is ever allowed. This is where that
 * assumption is checked rather than assumed.
 */
it('encodes exactly what the package would', function (string $payload) {
    $expected = Package::make()
        ->withMaxBytes(Converter::MAX_INPUT_BYTES)
        ->withMaxDepth(Converter::MAX_DEPTH)
        ->toJson($payload);

    expect(new Converter($payload)->output())->toBe($expected);
})->with([
    'null' => 'N;',
    'false' => 'b:0;',
    'integer' => 'i:42;',
    'negative integer' => 'i:-42;',
    'float' => 'd:1.5;',
    'string' => 's:6:"Chrome";',
    'empty string' => 's:0:"";',
    'non-ASCII string' => 's:5:"café";',
    'string with a slash' => 's:21:"https://example.com/a";',
    'string with a quote' => 's:3:"a"b";',
    'empty array' => 'a:0:{}',
    'list' => 'a:2:{i:0;i:1;i:1;i:2;}',
    'map' => 'a:1:{s:4:"name";s:6:"Chrome";}',
    'nested array' => 'a:1:{s:1:"a";a:1:{s:1:"b";a:1:{s:1:"c";i:1;}}}',
    'mixed keys' => 'a:2:{i:0;s:1:"a";s:3:"key";b:1;}',
    'array holding null' => 'a:1:{i:0;N;}',
    'deeply escaped string' => 'S:5:"\\68ello";',
]);

/**
 * A back reference resolves to the value it names, which both sides then write out at
 * each place it appears. Encoding it here rather than in the package must not change
 * that.
 */
it('encodes a resolved back reference exactly as the package would', function () {
    $shared = ['a' => 1];
    $payload = serialize(['first' => &$shared, 'second' => &$shared]);

    $expected = Package::make()
        ->withMaxBytes(Converter::MAX_INPUT_BYTES)
        ->withMaxDepth(Converter::MAX_DEPTH)
        ->toJson($payload);

    expect(new Converter($payload)->output())->toBe($expected);
});

it('encodes a real-world payload exactly as the package would', function () {
    $payload = 'a:10:{s:4:"name";s:6:"Chrome";s:7:"version";s:9:"103.0.0.0";s:8:"platform";s:7:"Windows";'
        .'s:10:"update_url";s:29:"https://www.google.com/chrome";s:7:"img_src";'
        .'s:44:"https://s.w.org/images/browsers/chrome.png?1";s:11:"img_src_ssl";'
        .'s:44:"https://s.w.org/images/browsers/chrome.png?1";s:15:"current_version";s:2:"18";'
        .'s:7:"upgrade";b:0;s:8:"insecure";b:0;s:6:"mobile";b:0;}';

    expect(new Converter($payload)->output())->toBe(Package::toJson($payload));
});
