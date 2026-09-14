<?php

namespace App\Livewire;

use App\Enums\ConversionInterface;
use App\Exceptions\ConversionException;
use App\Livewire\Forms\SerializedForm;
use App\Services\ConversionTelemetry;
use App\Services\DiagnosticPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Head\Facades\Head;
use Laravel\Head\Facades\Schema;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Serialized extends Component
{
    /**
     * The home page meta description, shared with the route metadata and the
     * structured data so both describe the converter the same way.
     *
     * @since 1.0.0
     */
    public const string META_DESCRIPTION = 'Convert PHP serialized data to readable JSON without storing your input. Includes tested mappings, limits, and object-safety guidance.';

    private const int MAX_ATTEMPTS = 10;

    /**
     * The serialized form.
     *
     * @since 1.0.0
     */
    public SerializedForm $form;

    public ?string $result = null;

    /**
     * The located syntax problem behind the most recent failed conversion.
     *
     * Locked because the panel renders whatever this holds. Blade escaping keeps
     * a tampered snapshot from becoming script, but not from putting arbitrary
     * wording in front of the next person to look at the page.
     *
     * @var null|array{
     *     code: string,
     *     message: string,
     *     offset: int,
     *     length: int,
     *     suggestion: string|null,
     *     line: int|null,
     *     column: int|null,
     *     excerpt: array{
     *         before: string,
     *         span: string,
     *         after: string,
     *         truncatedStart: bool,
     *         truncatedEnd: bool,
     *         spanTruncated: bool,
     *         caret: bool
     *     }
     * }
     */
    #[Locked]
    public ?array $diagnostic = null;

    /**
     * Mount the component.
     *
     * Publish the converter's structured data alongside the head metadata the
     * home route already declares.
     *
     * @since 1.0.0
     */
    public function mount(): void
    {
        Head::schema(
            Schema::webApplication()
                ->name('Unserialize')
                ->url(url('/').'/')
                ->description(self::META_DESCRIPTION)
                ->applicationCategory('DeveloperApplication')
                ->operatingSystem('Any operating system with a modern web browser')
                ->offers(
                    Schema::offer()
                        ->price(0)
                        ->currency('USD')
                )
        );
    }

    /**
     * Start the unserialize process.
     *
     * @since 1.0.0
     */
    public function unserialize(Request $request, ConversionTelemetry $telemetry, DiagnosticPresenter $presenter): void
    {
        $this->diagnostic = null;
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
            $telemetry->record(
                ConversionInterface::Browser,
                $exception->errorCode->value,
                $inputBytes,
                $startedAt,
                $exception->diagnostic?->code,
            );

            $this->result = null;
            $this->diagnostic = $exception->diagnostic === null
                ? null
                : $presenter->present($exception->diagnostic, $this->form->serializedData);

            /**
             * The inline field error stays: it is what gives the textarea its
             * `aria-invalid` state. The panel below carries the detail.
             */
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
