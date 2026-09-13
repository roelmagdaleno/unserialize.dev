<?php

namespace App\Exceptions;

use App\Enums\ConversionErrorCode;
use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class ConversionException extends Exception implements ShouldntReport
{
    public function __construct(public readonly ConversionErrorCode $errorCode)
    {
        parent::__construct($errorCode->message());
    }
}
