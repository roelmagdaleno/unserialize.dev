<?php

use App\Enums\EngineFailureKind;
use App\Enums\ScanVerdict;
use App\Enums\SyntaxErrorCode;
use App\Services\SerializedDiagnostics;
use App\Services\SerializedScanner;
use Tests\Support\SerializedEngine;
use Tests\Support\SerializedMutator;
use Tests\Support\SerializedValueGenerator;

/**
 * These tests are the accuracy proof for the scanner. They compare it against
 * PHP's own decoder over a generated corpus and its mutations, so a grammar gap
 * fails the suite instead of reaching a user as a misplaced red frame.
 */
$seeds = range(1, 25);

it('accepts every output of serialize() for the generated corpus', function (int $seed) {
    $scanner = new SerializedScanner;

    foreach ((new SerializedValueGenerator($seed))->corpus(100) as $value) {
        $serialized = serialize($value);
        $outcome = $scanner->scan($serialized);

        expect($outcome->verdict)->not->toBe(
            ScanVerdict::Invalid,
            sprintf('Rejected a serialize() output: %s', $outcome->diagnostic?->message ?? ''),
        )->and($outcome->consumedBytes)->toBe(strlen($serialized));
    }
})->with($seeds);

it('never rejects a payload that PHP accepts', function (int $seed) {
    $scanner = new SerializedScanner;
    $generator = new SerializedValueGenerator($seed);
    $mutator = new SerializedMutator($generator->randomizer());

    foreach ($generator->corpus(30) as $value) {
        foreach ($mutator->mutations(serialize($value)) as $name => $mutation) {
            if (SerializedEngine::outcome($mutation)->failed()) {
                continue;
            }

            expect($scanner->scan($mutation)->verdict)->not->toBe(
                ScanVerdict::Invalid,
                sprintf('Mutation "%s" was rejected although PHP accepted it.', $name),
            );
        }
    }
})->with($seeds);

it('reports a location PHP also blames', function (int $seed) {
    $scanner = new SerializedScanner;
    $generator = new SerializedValueGenerator($seed);
    $mutator = new SerializedMutator($generator->randomizer());

    foreach ($generator->corpus(30) as $value) {
        foreach ($mutator->mutations(serialize($value)) as $name => $mutation) {
            $engine = SerializedEngine::outcome($mutation);

            if ($engine->kind !== EngineFailureKind::SyntaxError) {
                continue;
            }

            $outcome = $scanner->scan($mutation);

            if ($outcome->verdict !== ScanVerdict::Invalid) {
                continue;
            }

            [$from, $to] = $outcome->diagnostic->claimInterval();

            expect($engine->offset)->toBeGreaterThanOrEqual($from, sprintf('Mutation "%s" claimed too late.', $name))
                ->and($engine->offset)->toBeLessThanOrEqual($to, sprintf('Mutation "%s" claimed too early.', $name));
        }
    }
})->with($seeds);

/**
 * PHP walks a declared string length byte by byte, so the offset it reports is
 * exactly where the closing quote should have been -- but only while that byte
 * is inside the payload. A length that overruns the buffer is rejected earlier
 * and from a different position, so exact agreement is claimed only for the
 * in-bounds case, which is the canonical search-and-replace corruption.
 */
it('matches PHP exactly for string length mismatches', function (int $seed) {
    $scanner = new SerializedScanner;
    $generator = new SerializedValueGenerator($seed);
    $mutator = new SerializedMutator($generator->randomizer());
    $compared = 0;

    foreach ($generator->corpus(30) as $value) {
        foreach ($mutator->mutations(serialize($value)) as $mutation) {
            $engine = SerializedEngine::outcome($mutation);
            $diagnostic = $scanner->scan($mutation)->diagnostic;

            if ($engine->kind !== EngineFailureKind::SyntaxError || $diagnostic?->code !== SyntaxErrorCode::StringLengthMismatch) {
                continue;
            }

            if ($diagnostic->expectedTerminatorOffset >= strlen($mutation)) {
                continue;
            }

            expect($diagnostic->expectedTerminatorOffset)->toBe($engine->offset);
            $compared++;
        }
    }

    expect($compared)->toBeGreaterThan(0);
})->with($seeds);

it('matches PHP exactly for trailing data', function (int $seed) {
    $scanner = new SerializedScanner;
    $generator = new SerializedValueGenerator($seed);
    $mutator = new SerializedMutator($generator->randomizer());

    foreach ($generator->corpus(30) as $value) {
        foreach ($mutator->mutations(serialize($value)) as $mutation) {
            $engine = SerializedEngine::outcome($mutation);
            $diagnostic = $scanner->scan($mutation)->diagnostic;

            if ($engine->kind !== EngineFailureKind::ExtraData || $diagnostic?->code !== SyntaxErrorCode::TrailingData) {
                continue;
            }

            expect($diagnostic->offset)->toBe($engine->offset);
        }
    }
})->with($seeds);

it('always produces a diagnostic inside the input bounds', function (int $seed) {
    $diagnostics = new SerializedDiagnostics(new SerializedScanner);
    $generator = new SerializedValueGenerator($seed);
    $mutator = new SerializedMutator($generator->randomizer());

    foreach ($generator->corpus(30) as $value) {
        foreach ($mutator->mutations(serialize($value)) as $name => $mutation) {
            $engine = SerializedEngine::outcome($mutation);

            if (! $engine->failed()) {
                continue;
            }

            $diagnostic = $diagnostics->diagnose($mutation, $engine);

            expect($diagnostic->offset)->toBeGreaterThanOrEqual(0, $name)
                ->and($diagnostic->length)->toBeGreaterThanOrEqual(0, $name)
                ->and($diagnostic->offset + $diagnostic->length)->toBeLessThanOrEqual(strlen($mutation), $name)
                ->and($diagnostic->message)->not->toBe('');
        }
    }
})->with($seeds);

it('only publishes a suggested correction that resolves the problem it names', function (int $seed) {
    $diagnostics = new SerializedDiagnostics(new SerializedScanner);
    $scanner = new SerializedScanner;
    $generator = new SerializedValueGenerator($seed);
    $mutator = new SerializedMutator($generator->randomizer());
    $verified = 0;

    foreach ($generator->corpus(30) as $value) {
        foreach ($mutator->mutations(serialize($value)) as $mutation) {
            $engine = SerializedEngine::outcome($mutation);

            if (! $engine->failed()) {
                continue;
            }

            $diagnostic = $diagnostics->diagnose($mutation, $engine);
            $fix = $diagnostic->fix;

            if ($diagnostic->suggestion === null || $fix === null) {
                continue;
            }

            $corrected = substr_replace($mutation, $fix['replacement'], $fix['offset'], $fix['length']);
            $outcome = $scanner->scan($corrected);
            $verified++;

            if ($outcome->verdict !== ScanVerdict::Invalid) {
                continue;
            }

            expect([$outcome->diagnostic->code, $outcome->diagnostic->offset])->not->toBe(
                [$diagnostic->code, $diagnostic->offset],
                'A suggested correction was published that leaves the same complaint in place.',
            );
        }
    }

    expect($verified)->toBeGreaterThan(0);
})->with($seeds);

it('produces every syntax error code across the mutation corpus', function () {
    $scanner = new SerializedScanner;
    $seen = [];

    foreach (range(1, 25) as $seed) {
        $generator = new SerializedValueGenerator($seed);
        $mutator = new SerializedMutator($generator->randomizer());

        foreach ($generator->corpus(30) as $value) {
            foreach ($mutator->mutations(serialize($value)) as $mutation) {
                $diagnostic = $scanner->scan($mutation)->diagnostic;

                if ($diagnostic !== null) {
                    $seen[$diagnostic->code->value] = true;
                }
            }
        }
    }

    expect(array_keys($seen))->toContain(
        SyntaxErrorCode::ArrayCountMismatch->value,
        SyntaxErrorCode::InvalidArrayKey->value,
        SyntaxErrorCode::MalformedNumber->value,
        SyntaxErrorCode::MissingDelimiter->value,
        SyntaxErrorCode::MissingTerminator->value,
        SyntaxErrorCode::StringLengthMismatch->value,
        SyntaxErrorCode::TrailingData->value,
        SyntaxErrorCode::UnexpectedEnd->value,
        SyntaxErrorCode::UnknownTypeMarker->value,
    );
});
