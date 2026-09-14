<?php

use App\Data\SyntaxDiagnostic;
use App\Enums\SyntaxErrorCode;
use App\Services\DiagnosticPresenter;

function present(string $data, int $offset, int $length): array
{
    return (new DiagnosticPresenter)->present(
        new SyntaxDiagnostic(SyntaxErrorCode::StringLengthMismatch, $offset, $length, 'Example.'),
        $data,
    );
}

it('splits the value around the reported region', function () {
    $excerpt = present('a:10:{s:4:"names";N;}', 6, 11)['excerpt'];

    expect($excerpt['before'])->toBe('a:10:{')
        ->and($excerpt['span'])->toBe('s:4:"names"')
        ->and($excerpt['after'])->toBe(';N;}')
        ->and($excerpt['truncatedStart'])->toBeFalse()
        ->and($excerpt['truncatedEnd'])->toBeFalse();
});

it('keeps only a window around the region of a large value', function () {
    $data = str_repeat('x', 5_000).'BROKEN'.str_repeat('y', 5_000);

    $excerpt = present($data, 5_000, 6)['excerpt'];

    expect($excerpt['span'])->toBe('BROKEN')
        ->and(strlen($excerpt['before']))->toBe(DiagnosticPresenter::WINDOW_BYTES)
        ->and(strlen($excerpt['after']))->toBe(DiagnosticPresenter::WINDOW_BYTES)
        ->and($excerpt['truncatedStart'])->toBeTrue()
        ->and($excerpt['truncatedEnd'])->toBeTrue();
});

it('shortens a region too large to paint', function () {
    $data = str_repeat('z', 5_000);

    $excerpt = present($data, 0, 5_000)['excerpt'];

    expect($excerpt['spanTruncated'])->toBeTrue()
        ->and(mb_strlen($excerpt['span']))->toBe(DiagnosticPresenter::MAX_SPAN_BYTES + 1);
});

it('gives a zero-length region one byte to paint as a caret', function () {
    $excerpt = present('b:0', 3, 0)['excerpt'];

    expect($excerpt['caret'])->toBeTrue()
        ->and($excerpt['span'])->toBe(' ');
});

it('escapes bytes that are not valid UTF-8', function () {
    $excerpt = present("a:1:{\xFF\xFE};", 5, 2)['excerpt'];

    expect($excerpt['span'])->toBe('\xFF\xFE')
        ->and(mb_check_encoding($excerpt['span'], 'UTF-8'))->toBeTrue();
});

it('escapes unprintable bytes but keeps newlines and tabs', function () {
    $excerpt = present("a\x00b\tc\nd", 0, 7)['excerpt'];

    expect($excerpt['span'])->toBe("a\\x00b\tc\nd");
});

it('leaves valid multi-byte characters intact', function () {
    $excerpt = present('café 😀', 0, strlen('café 😀'))['excerpt'];

    expect($excerpt['span'])->toBe('café 😀');
});

it('reports no line or column for a single-line value', function () {
    $presented = present('a:10:{s:4:"names";N;}', 6, 11);

    expect($presented['line'])->toBeNull()
        ->and($presented['column'])->toBeNull();
});

it('counts lines and columns in bytes', function () {
    $presented = present("first\nsecond", 7, 1);

    expect($presented['line'])->toBe(2)
        ->and($presented['column'])->toBe(2);
});
