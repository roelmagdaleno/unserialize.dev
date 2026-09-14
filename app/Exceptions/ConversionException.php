<?php

namespace App\Exceptions;

use App\Data\SyntaxDiagnostic;
use App\Enums\ConversionErrorCode;
use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class ConversionException extends Exception implements ShouldntReport
{
    /**
     * The diagnostic is an optional addition, never a replacement: the error
     * code and the exception message keep their exact published values, so
     * every existing caller behaves identically and each surface opts in to the
     * extra detail deliberately rather than inheriting it.
     */
    public function __construct(
        public readonly ConversionErrorCode $errorCode,
        public readonly ?SyntaxDiagnostic $diagnostic = null,
    ) {
        parent::__construct($errorCode->message());
    }
}
