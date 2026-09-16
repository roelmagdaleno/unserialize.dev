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
 * This class decides only what a completed scan means: whether the payload was
 * empty, whether a depth limit makes the verdict unverifiable, whether bytes
 * are left over, and which {@see ScanOutcome} follows. The grammar itself lives
 * in {@see ValueParser} and the rule families beneath it.
 *
 * The scanner exists to explain a failure, never to decide one. It is consulted
 * only after `unserialize()` has already rejected a payload, so a bug in this
 * grammar can degrade a message but can never reject a value PHP accepts.
 *
 * Two limits are deliberate rather than accidental:
 *
 * - Exact offset agreement with PHP is not achievable in general. PHP reports
 *   wherever its own lexer stopped, which is the element start for `b:2;` and
 *   one byte past the token for `r:1;`. The guarantee this class supports is
 *   containment: the byte PHP blames falls inside {@see SyntaxDiagnostic::claimInterval()}.
 *   Exact equality holds only for string length mismatches and trailing data.
 * - `E:`, `C:`, `r:`, and `R:` are validated for shape only. Their real validity
 *   depends on class resolution and PHP's internal value numbering, and
 *   reproducing either on untrusted input is a larger accuracy risk than
 *   declining to judge them.
 * - A suggested string length is measured to the first `";` ahead of the
 *   contents. When the contents themselves hold that pair the suggestion
 *   restores a value that decodes rather than the value the author meant, since
 *   nothing in a corrupted payload records the original intent. The message
 *   always names the same byte count it suggests, so the two never disagree.
 */
class SerializedScanner
{
    /**
     * Republished from {@see ValueParser} so callers that reason about nesting
     * depth keep talking to the scanner rather than to its internals.
     */
    public const int MAX_DEPTH = ValueParser::MAX_DEPTH;

    private readonly SyntaxDiagnosticFactory $diagnostics;

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
