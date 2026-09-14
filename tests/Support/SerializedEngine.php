<?php

namespace Tests\Support;

use App\Data\EngineOutcome;
use App\Services\Serialized;
use Throwable;

/**
 * Runs PHP's own decoder the way the application does, so tests can compare the
 * scanner against the exact verdict the application will see.
 */
final class SerializedEngine
{
    public static function outcome(string $data): EngineOutcome
    {
        $warnings = [];

        set_error_handler(static function (int $number, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $value = unserialize($data, [
                'allowed_classes' => false,
                'max_depth' => Serialized::MAX_DEPTH,
            ]);
        } catch (Throwable) {
            $value = false;
        } finally {
            restore_error_handler();
        }

        return EngineOutcome::fromWarnings($warnings, $value, $data);
    }
}
