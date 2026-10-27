<?php

use App\Enums\InputTrait;
use App\Services\InputTraitDetector;

it('detects how a value was damaged on its way in', function (string $value, array $traits) {
    expect((new InputTraitDetector)->detect($value))->toBe($traits);
})->with([
    'clean value' => ['a:1:{s:4:"name";s:3:"Ada";}', []],
    'surrounding whitespace' => [" \na:0:{}\n", [InputTrait::LeadingWhitespace, InputTrait::TrailingWhitespace]],
    'SQL quotes' => ["'a:1:{i:0;s:1:\"x\";}'", [InputTrait::WrappedInQuotes]],
    'escaped quotes' => ['a:1:{i:0;s:1:\"x\";}', [InputTrait::EscapedQuotes]],
    'quoted and escaped' => ['"a:1:{i:0;s:1:\"x\";}"', [InputTrait::WrappedInQuotes, InputTrait::EscapedQuotes]],
    'JSON object' => ['{"name": "Ada"}', [InputTrait::LooksLikeJson]],
    'JSON array' => ['[1, 2]', [InputTrait::LooksLikeJson]],
    'base64' => [base64_encode('a:1:{i:0;s:1:"x";}'), [InputTrait::LooksLikeBase64]],
    'empty value' => ['   ', []],
]);

it('does not mistake serialized values for base64 or JSON', function (string $value) {
    expect((new InputTraitDetector)->detect($value))->toBe([]);
})->with([
    'integer' => ['i:42;'],
    'null' => ['N;'],
    'short token' => ['YWJj'],
    'broken JSON-like braces' => ['{not json'],
]);
