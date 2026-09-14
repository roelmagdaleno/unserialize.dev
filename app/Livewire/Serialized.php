<?php

namespace App\Livewire;

use App\Enums\ConversionInterface;
use App\Exceptions\ConversionException;
use App\Livewire\Forms\SerializedForm;
use App\Services\ConversionTelemetry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Serialized extends Component
{
    private const int MAX_ATTEMPTS = 10;

    /**
     * The serialized form.
     *
     * @since 1.0.0
     */
    public SerializedForm $form;

    public ?string $result = null;

    /**
     * Start the unserialize process.
     *
     * @since 1.0.0
     */
    public function unserialize(Request $request, ConversionTelemetry $telemetry): void
    {
        $startedAt = hrtime(true);
        $inputBytes = strlen($this->form->serializedData);
        $rateLimitKey = 'unserialize:'.hash('sha256', $request->ip());

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_ATTEMPTS)) {
            $telemetry->record(ConversionInterface::Browser, 'rate_limited', $inputBytes, $startedAt);
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->addError(
                'form.serializedData',
                "Too many conversion attempts. Please try again in {$seconds} seconds.",
            );

            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        try {
            $this->result = $this->form->submit()->json;
            $telemetry->record(ConversionInterface::Browser, 'success', $inputBytes, $startedAt);
        } catch (ValidationException $exception) {
            $telemetry->record(ConversionInterface::Browser, 'validation_error', $inputBytes, $startedAt);

            throw $exception;
        } catch (ConversionException $exception) {
            $telemetry->record(ConversionInterface::Browser, $exception->errorCode->value, $inputBytes, $startedAt);
            $this->result = null;
            $this->addError('form.serializedData', $exception->getMessage());
        }
    }

    /**
     * Render the view.
     *
     * @since 1.0.0
     */
    public function render(): View
    {
        return view('livewire.serialized');
    }
}
