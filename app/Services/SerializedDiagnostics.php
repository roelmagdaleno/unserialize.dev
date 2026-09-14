<?php

namespace App\Services;

use App\Data\EngineOutcome;
use App\Data\SyntaxDiagnostic;
use App\Enums\DiagnosticConfidence;
use App\Enums\EngineFailureKind;
use App\Enums\ScanVerdict;
use App\Enums\SyntaxErrorCode;

/**
 * Reconciles the scanner's explanation with PHP's own reported location.
 *
 * PHP always knows where it stopped; it just cannot say why. The scanner
 * supplies the why. When the two locations agree the scanner's account is
 * published, and when they do not, PHP's location wins and the explanation is
 * softened. That ordering is what makes the promise "the byte PHP blames always
 * falls inside the region shown to the user" hold for every input.
 */
class SerializedDiagnostics
{
    public function __construct(private readonly SerializedScanner $scanner) {}

    /**
     * Produce one diagnostic for a payload PHP has already rejected.
     *
     * This never throws and always returns a diagnostic whose region lies
     * inside the payload.
     */
    public function diagnose(string $data, EngineOutcome $engine): SyntaxDiagnostic
    {
        $length = strlen($data);

        /**
         * PHP reports an exhausted depth budget through the same
         * "Error at offset" warning as a genuine syntax error, so the kind is
         * honoured before the offset is read as a syntax location.
         */
        if ($engine->kind === EngineFailureKind::DepthExceeded) {
            $offset = $this->clampOffset($engine->offset ?? 0, $length);

            return new SyntaxDiagnostic(
                SyntaxErrorCode::DepthLimitExceeded,
                $offset,
                0,
                sprintf('The value nests deeper than the %d levels this converter decodes.', Serialized::MAX_DEPTH),
                'Convert a smaller part of the structure.',
                contextStart: $offset,
                engineOffset: $engine->offset,
                confidence: DiagnosticConfidence::Fallback,
            );
        }

        $outcome = $this->scanner->scan($data);
        $engineOffset = $engine->offset;

        if ($outcome->verdict === ScanVerdict::Invalid && $outcome->diagnostic !== null) {
            $diagnostic = $this->verifySuggestion($data, $outcome->diagnostic);

            if ($engineOffset === null) {
                return $diagnostic->corroboratedBy(null, DiagnosticConfidence::Exact)->clampedTo($length);
            }

            [$from, $to] = $diagnostic->claimInterval();

            if ($engineOffset >= $from && $engineOffset <= $to) {
                return $diagnostic->corroboratedBy($engineOffset, DiagnosticConfidence::Exact)->clampedTo($length);
            }

            /**
             * The scanner and PHP point at different places. PHP is the
             * authority on where decoding stopped, so its offset is published
             * and the scanner's unproven correction is dropped.
             */
            return $this->fromEngineOffset($diagnostic->code, $engineOffset, $length, DiagnosticConfidence::Approximate);
        }

        if ($engineOffset !== null) {
            return $this->fromEngineOffset(SyntaxErrorCode::UnknownSyntaxError, $engineOffset, $length, DiagnosticConfidence::Fallback);
        }

        return new SyntaxDiagnostic(
            SyntaxErrorCode::UnexpectedEnd,
            0,
            min(1, $length),
            'This value could not be decoded and PHP did not report where it stopped.',
            confidence: DiagnosticConfidence::Fallback,
        );
    }

    /**
     * Build a diagnostic anchored on PHP's offset alone.
     */
    private function fromEngineOffset(SyntaxErrorCode $code, int $engineOffset, int $length, DiagnosticConfidence $confidence): SyntaxDiagnostic
    {
        $offset = $this->clampOffset($engineOffset, $length);

        return new SyntaxDiagnostic(
            $code,
            $offset,
            min(1, $length - $offset),
            sprintf('PHP stopped reading this value at byte %d.', $engineOffset),
            contextStart: $offset,
            engineOffset: $engineOffset,
            confidence: $confidence,
        );
    }

    /**
     * Publish a suggested correction only after proving it resolves the problem.
     *
     * The candidate length is derived from the next `";` in the payload, which
     * is a guess whenever the string itself contains that pair. Applying the
     * edit and rescanning turns the guess into a fact: if the very same
     * complaint still lands on the very same byte, the edit changed nothing and
     * the suggestion is withheld.
     *
     * The test is deliberately local rather than "the whole value now decodes",
     * because a payload mangled by a search-and-replace pass usually carries
     * several corruptions and no single edit can clear all of them.
     */
    private function verifySuggestion(string $data, SyntaxDiagnostic $diagnostic): SyntaxDiagnostic
    {
        if ($diagnostic->fix === null) {
            return $diagnostic;
        }

        $outcome = $this->scanner->scan(substr_replace(
            $data,
            $diagnostic->fix['replacement'],
            $diagnostic->fix['offset'],
            $diagnostic->fix['length'],
        ));

        if ($outcome->verdict !== ScanVerdict::Invalid) {
            return $diagnostic;
        }

        $remaining = $outcome->diagnostic;

        return $remaining->code === $diagnostic->code && $remaining->offset === $diagnostic->offset
            ? $diagnostic->withoutSuggestion()
            : $diagnostic;
    }

    private function clampOffset(int $offset, int $length): int
    {
        return max(0, min($offset, $length));
    }
}
