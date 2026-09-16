<?php

namespace App\Exceptions;

use App\Data\SyntaxDiagnostic;
use App\Enums\ConversionErrorCode;
use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * A payload that could not be converted, and why.
 *
 * Not reported to the error tracker: a refused conversion is an answer to the
 * caller, not an application fault.
 */
class ConversionException extends Exception implements ShouldntReport
{
    /**
     * The diagnostic is an optional addition, never a replacement: the error
     * code and the exception message keep their published values, so each
     * surface opts in to the extra detail rather than inheriting it.
     *
     * @param  ConversionErrorCode  $errorCode  Why the conversion was refused.
     * @param  SyntaxDiagnostic|null  $diagnostic  Where the payload broke, when it could be located.
     */
    public function __construct(
        public readonly ConversionErrorCode $errorCode,
        public readonly ?SyntaxDiagnostic $diagnostic = null,
    ) {
        parent::__construct($errorCode->message());
    }
}
