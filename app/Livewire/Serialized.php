<?php

namespace App\Livewire;

use App\Livewire\Forms\SerializedForm;
use Exception;
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

    /**
     * Start the unserialize process.
     *
     * @since 1.0.0
     *
     * @throws Exception If unserialize fails.
     */
    public function unserialize(Request $request): void
    {
        $rateLimitKey = 'unserialize:'.hash('sha256', $request->ip());

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->addError(
                'form.serializedData',
                "Too many conversion attempts. Please try again in {$seconds} seconds.",
            );

            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        try {
            $output = $this->form->submit();
            $this->redirectRoute('outputs', $output);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Exception $exception) {
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
