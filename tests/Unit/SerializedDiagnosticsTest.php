<?php

use App\Data\EngineOutcome;
use App\Enums\DiagnosticConfidence;
use App\Enums\EngineFailureKind;
use App\Enums\SyntaxErrorCode;
use App\Services\SerializedDiagnostics;
use App\Services\SerializedScanner;

function diagnostics(): SerializedDiagnostics
{
    return new SerializedDiagnostics(new SerializedScanner);
}

it('classifies benign diagnostics as success', function (string $warning) {
    $outcome = EngineOutcome::fromWarnings([$warning], 'decoded', 'irrelevant');

    expect($outcome->failed())->toBeFalse()
        ->and($outcome->kind)->toBe(EngineFailureKind::None);
})->with([
    'deprecated escaped string format' => "unserialize(): Unserializing the 'S' format is deprecated",
    'saturated integer' => 'unserialize(): Numerical result out of range',
]);

it('classifies exhausted nesting ahead of the offset it reports alongside it', function () {
    $outcome = EngineOutcome::fromWarnings([
        'unserialize(): Maximum depth of 512 exceeded. The depth limit can be changed using the max_depth unserialize() option',
        'unserialize(): Error at offset 4613 of 6002 bytes',
    ], false, 'irrelevant');

    expect($outcome->kind)->toBe(EngineFailureKind::DepthExceeded)
        ->and($outcome->offset)->toBe(4613);
});

it('classifies a failing class unserializer as an object problem', function () {
    $outcome = EngineOutcome::fromWarnings([
        'unserialize(): Class __PHP_Incomplete_Class has no unserializer',
    ], false, 'irrelevant');

    expect($outcome->kind)->toBe(EngineFailureKind::ObjectUnserializer)
        ->and($outcome->offset)->toBeNull();
});

it('classifies trailing bytes ahead of the offset it reports alongside them', function () {
    $outcome = EngineOutcome::fromWarnings([
        'unserialize(): Extra data starting at offset 4 of 11 bytes',
    ], 1, 'i:1;garbage');

    expect($outcome->kind)->toBe(EngineFailureKind::ExtraData)
        ->and($outcome->offset)->toBe(4);
});

it('classifies a silent false return as an unlocated failure', function () {
    $outcome = EngineOutcome::fromWarnings([], false, 'anything');

    expect($outcome->kind)->toBe(EngineFailureKind::Opaque);
});

it('does not mistake a legitimately decoded false for a failure', function () {
    expect(EngineOutcome::fromWarnings([], false, 'b:0;')->failed())->toBeFalse();
});

it('prefers the scanner location when PHP blames a byte inside it', function () {
    $serializedData = 'a:10:{s:4:"names";s:6:"Chrome";}';

    $diagnostic = diagnostics()->diagnose($serializedData, new EngineOutcome(EngineFailureKind::SyntaxError, 15));

    expect($diagnostic->code)->toBe(SyntaxErrorCode::StringLengthMismatch)
        ->and($diagnostic->offset)->toBe(6)
        ->and($diagnostic->confidence)->toBe(DiagnosticConfidence::Exact)
        ->and($diagnostic->engineOffset)->toBe(15);
});

it('falls back to the PHP offset when the scanner blames somewhere else', function () {
    $serializedData = 'a:10:{s:4:"names";s:6:"Chrome";}';

    $diagnostic = diagnostics()->diagnose($serializedData, new EngineOutcome(EngineFailureKind::SyntaxError, 30));

    expect($diagnostic->offset)->toBe(30)
        ->and($diagnostic->confidence)->toBe(DiagnosticConfidence::Approximate)
        ->and($diagnostic->suggestion)->toBeNull()
        ->and($diagnostic->message)->toBe('PHP stopped reading this value at byte 30.');
});

it('falls back to the PHP offset when the scanner finds nothing to explain', function () {
    $diagnostic = diagnostics()->diagnose('a:1:{i:0;r:9;}', new EngineOutcome(EngineFailureKind::SyntaxError, 13));

    expect($diagnostic->code)->toBe(SyntaxErrorCode::UnknownSyntaxError)
        ->and($diagnostic->offset)->toBe(13)
        ->and($diagnostic->confidence)->toBe(DiagnosticConfidence::Fallback);
});

it('reports exhausted nesting rather than a syntax error', function () {
    $diagnostic = diagnostics()->diagnose('a:1:{i:0;N;}', new EngineOutcome(EngineFailureKind::DepthExceeded, 7));

    expect($diagnostic->code)->toBe(SyntaxErrorCode::DepthLimitExceeded)
        ->and($diagnostic->offset)->toBe(7)
        ->and($diagnostic->confidence)->toBe(DiagnosticConfidence::Fallback);
});

it('still produces a diagnostic when PHP reports no location at all', function () {
    $diagnostic = diagnostics()->diagnose('a:1:{i:0;N;}', new EngineOutcome(EngineFailureKind::Opaque));

    expect($diagnostic->offset)->toBe(0)
        ->and($diagnostic->message)->not->toBe('');
});

it('keeps every diagnostic inside the payload even when PHP reports past its end', function () {
    $diagnostic = diagnostics()->diagnose('i:1;', new EngineOutcome(EngineFailureKind::SyntaxError, 9_999));

    expect($diagnostic->offset)->toBeLessThanOrEqual(4)
        ->and($diagnostic->offset + $diagnostic->length)->toBeLessThanOrEqual(4);
});

it('bases a correction on the first closing quote when the contents contain one', function () {
    /**
     * The real contents are `a";b`, but nothing in the payload says so. The
     * correction restores a value that decodes, not necessarily the value the
     * author meant, and the message names the same byte count it suggests.
     */
    $diagnostic = diagnostics()->diagnose('s:7:"a";b";', new EngineOutcome(EngineFailureKind::SyntaxError, 12));

    expect($diagnostic->code)->toBe(SyntaxErrorCode::StringLengthMismatch)
        ->and($diagnostic->message)->toContain('1 bytes precede the closing quote')
        ->and($diagnostic->suggestion)->toBe('Change `s:7:` to `s:1:`.');
});

it('publishes a correction only when applying it changes the complaint', function (string $serializedData) {
    $diagnostic = diagnostics()->diagnose($serializedData, new EngineOutcome(EngineFailureKind::Opaque));

    if ($diagnostic->suggestion === null || $diagnostic->fix === null) {
        return;
    }

    $corrected = substr_replace(
        $serializedData,
        $diagnostic->fix['replacement'],
        $diagnostic->fix['offset'],
        $diagnostic->fix['length'],
    );

    $remaining = (new SerializedScanner)->scan($corrected)->diagnostic;

    expect($remaining === null || $remaining->code !== $diagnostic->code || $remaining->offset !== $diagnostic->offset)
        ->toBeTrue();
})->with([
    'short string length' => 'a:1:{s:4:"names";N;}',
    'long string length' => 's:9:"hello";',
    'missing array elements' => 'a:5:{i:0;N;}',
    'surplus array elements' => 'a:1:{i:0;N;i:1;N;}',
    'quote inside contents' => 's:7:"a";b";',
]);
