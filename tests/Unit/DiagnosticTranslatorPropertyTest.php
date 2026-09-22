<?php

use App\Enums\SyntaxErrorCode;
use App\Exceptions\ConversionException;
use App\Services\Serialized;
use Tests\Support\SerializedMutator;
use Tests\Support\SerializedValueGenerator;

/**
 * The property the application still owns after the grammar moved into the package.
 *
 * Whether a payload is well formed is now `roelmagdaleno/serialized`'s question, and
 * its own suite answers it against PHP. What stays here is the translation: every
 * refusal this application publishes has to name a category it declares and point at
 * a byte range inside the payload the caller sent, because the browser panel slices
 * the payload with those two numbers and the API hands them to clients to do the
 * same. A diagnostic pointing past the end is a crash on the error path, which is
 * exactly when the user already has a problem.
 *
 * The corpus is generated and then corrupted, so the assertion runs over thousands
 * of broken payloads rather than the handful anyone would think to write by hand.
 */
$seeds = range(1, 25);

it('publishes a category it declares and a range inside the payload', function (int $seed) {
    $generator = new SerializedValueGenerator($seed);
    $mutator = new SerializedMutator($generator->randomizer());
    $declared = SyntaxErrorCode::cases();

    foreach ($generator->corpus(30) as $value) {
        foreach ($mutator->mutations(serialize($value)) as $name => $mutation) {
            try {
                new Serialized($mutation)->convert();
            } catch (ConversionException $exception) {
                $diagnostic = $exception->diagnostic;

                if ($diagnostic === null) {
                    continue;
                }

                expect($diagnostic->code)->toBeIn($declared, sprintf('Mutation "%s" published an undeclared category.', $name))
                    ->and($diagnostic->offset)->toBeGreaterThanOrEqual(0, sprintf('Mutation "%s" reported a negative offset.', $name))
                    ->and($diagnostic->length)->toBeGreaterThanOrEqual(0, sprintf('Mutation "%s" reported a negative length.', $name))
                    ->and($diagnostic->offset + $diagnostic->length)->toBeLessThanOrEqual(
                        strlen($mutation),
                        sprintf('Mutation "%s" reported a range past the end of the payload.', $name),
                    );
            }
        }
    }
})->with($seeds);

/**
 * A payload `serialize()` produced must convert back.
 *
 * This is the one direction a bug here costs a user real functionality: refusing
 * something PHP itself wrote. Values JSON has no form for at all are skipped, because
 * no converter could carry them -- the corpus generates `NAN` and `INF` floats, which
 * `json_encode()` refuses whatever they were serialized from.
 */
it('converts every output of serialize() that JSON can carry', function (int $seed) {
    foreach ((new SerializedValueGenerator($seed))->corpus(100) as $value) {
        if (json_encode($value) === false) {
            continue;
        }

        $serialized = serialize($value);

        expect(fn () => new Serialized($serialized)->convert())->not->toThrow(ConversionException::class);
    }
})->with($seeds);

/**
 * The excerpt the browser panel renders is cut with the diagnostic's own numbers, so
 * a range that slices cleanly here is one the panel can render without checking.
 */
it('reports a range that slices the payload it describes', function (int $seed) {
    $generator = new SerializedValueGenerator($seed);
    $mutator = new SerializedMutator($generator->randomizer());

    foreach ($generator->corpus(20) as $value) {
        foreach ($mutator->mutations(serialize($value)) as $name => $mutation) {
            try {
                new Serialized($mutation)->convert();
            } catch (ConversionException $exception) {
                if ($exception->diagnostic === null) {
                    continue;
                }

                $span = substr($mutation, $exception->diagnostic->offset, $exception->diagnostic->length);

                expect(strlen($span))->toBe(
                    $exception->diagnostic->length,
                    sprintf('Mutation "%s" reported a length the payload cannot supply.', $name),
                );
            }
        }
    }
})->with($seeds);
