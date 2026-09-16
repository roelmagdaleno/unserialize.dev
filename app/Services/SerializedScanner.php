<?php

namespace App\Services;

use App\Data\ScanOutcome;
use App\Data\SyntaxDiagnostic;
use App\Enums\SyntaxErrorCode;
use App\Services\Scanner\ScannerCursor;
use App\Services\Scanner\SyntaxDiagnosticFactory;
use App\Services\Scanner\TokenReader;
use App\Services\Scanner\ValueParser;

/**
 * Entry point to the recursive-descent scan over the PHP serialization grammar.
 *
 * This class decides only what a completed scan means: empty payload, depth
 * limit, leftover bytes, and which {@see ScanOutcome} follows. The grammar
 * lives in {@see ValueParser} and the rule families beneath it.
 *
 * The scanner explains a failure, never decides one. It is consulted only after
 * `unserialize()` has already rejected a payload, so a bug here can degrade a
 * message but can never reject a value PHP accepts. Three limits follow:
 *
 * - Offsets guarantee containment, not equality. PHP reports wherever its own
 *   lexer stopped, so the promise is that the byte PHP blames falls inside
 *   {@see SyntaxDiagnostic::claimInterval()}. Exact agreement holds only for
 *   string length mismatches and trailing data.
 * - `E:`, `C:`, `r:` and `R:` are validated for shape only, because their real
 *   validity depends on class resolution and PHP's internal value numbering.
 * - A suggested string length is measured to the first `";` ahead of the
 *   contents, so when the contents hold that pair the suggestion restores a
 *   value that decodes rather than the one the author meant. The message always
 *   names the same byte count it suggests, so the two never disagree.
 */
class SerializedScanner
{
    /**
     * Republished from {@see ValueParser} so callers that reason about nesting
     * depth keep talking to the scanner rather than to its internals.
     */
    public const int MAX_DEPTH = ValueParser::MAX_DEPTH;

    /**
     * Phrases the sentence for every problem the grammar finds.
     */
    private readonly SyntaxDiagnosticFactory $diagnostics;

    /**
     * The grammar itself, dispatching on each value's type marker.
     */
    private readonly ValueParser $parser;

    /**
     * Defaulted rather than injected: the scanner is built directly with
     * `new SerializedScanner` in {@see Serialized} and across the
     * test suite, and the factory is stateless, so there is nothing to wire.
     */
    public function __construct(SyntaxDiagnosticFactory $diagnostics = new SyntaxDiagnosticFactory)
    {
        $this->diagnostics = $diagnostics;
        $this->parser = new ValueParser(new TokenReader($diagnostics));
    }

    /**
     * Scan a payload and report the first problem that breaks the grammar.
     */
    public function scan(string $data): ScanOutcome
    {
        $length = strlen($data);

        if ($length === 0) {
            return ScanOutcome::invalid($this->diagnostics->emptyValue());
        }

        $cursor = new ScannerCursor($data, $length);
        $diagnostic = $this->parser->value($cursor, 0);

        if ($diagnostic !== null && $diagnostic->code === SyntaxErrorCode::DepthLimitExceeded) {
            return ScanOutcome::unverifiable();
        }

        if ($diagnostic !== null) {
            return ScanOutcome::invalid($diagnostic);
        }

        if ($cursor->position < $length) {
            return ScanOutcome::invalid($this->diagnostics->trailingData($cursor->position, $length));
        }

        return $cursor->unverifiable
            ? ScanOutcome::unverifiable($cursor->position)
            : ScanOutcome::valid($cursor->position);
    }
}
