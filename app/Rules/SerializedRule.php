<?php

namespace App\Rules;

use App\Services\Serialized;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SerializedRule implements ValidationRule
{
    /**
     * {@inheritDoc}
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $serialized = new Serialized($value);

        if ($serialized->isValid()) {
            return;
        }

        $fail('The data is not valid serialized data.');
    }
}
