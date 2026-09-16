<?php

namespace App\Livewire;

use App\Data\UsageContext;
use App\Enums\ConversionInterface;
use App\Enums\ConversionOutcome;
use App\Enums\UsageEventType;
use App\Exceptions\ConversionException;
use App\Livewire\Forms\SerializedForm;
use App\Services\ConversionRateLimiter;
use App\Services\ConversionTelemetry;
use App\Services\DiagnosticPresenter;
use App\Services\UsageEventRecorder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Head\Facades\Head;
use Laravel\Head\Facades\Schema;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
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

    /**
     * The status a Livewire update answers with.
     *
     * Livewire reports a functional failure inside a successful response, so
     * this is the transport result only. The event's `outcome` is what says
     * whether the conversion itself succeeded.
     */
    private const int TRANSPORT_STATUS = 200;

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
    public function unserialize(
        Request $request,
        ConversionTelemetry $telemetry,
        DiagnosticPresenter $presenter,
        ConversionRateLimiter $limiter,
    ): void {
        $this->diagnostic = null;
        $startedAt = hrtime(true);
        $inputBytes = strlen($this->form->serializedData);
        $rateLimitKey = 'unserialize:'.$limiter->key($request);

        /**
         * A browser conversion is an XHR, so the observed request URL is
         * `/livewire/update` and the page that hosted it arrives as the
         * referrer. Neither is inferred from the submitted payload.
         */
        $context = fn (?string $resultType = null): UsageContext => UsageContext::fromRequest(
            $request,
            httpStatus: self::TRANSPORT_STATUS,
            resultType: $resultType,
        );

        if (RateLimiter::tooManyAttempts($rateLimitKey, $limiter->attemptsPerMinute())) {
            $limiter->reject(ConversionInterface::Browser, $inputBytes, $startedAt, $context());

            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->addError(
                'form.serializedData',
                "Too many conversion attempts. Please try again in $seconds seconds.",
            );

            return;
        }

        RateLimiter::hit($rateLimitKey);

        try {
            $conversion = $this->form->submit();
            $this->result = $conversion->json;
            $telemetry->record(
                ConversionInterface::Browser,
                ConversionOutcome::Success,
                $inputBytes,
                $startedAt,
                null,
                $context(UsageContext::resultTypeFor($conversion->value)),
            );
        } catch (ValidationException $exception) {
            $telemetry->record(
                ConversionInterface::Browser,
                ConversionOutcome::ValidationError,
                $inputBytes,
                $startedAt,
                null,
                $context(),
            );

            throw $exception;
        } catch (ConversionException $exception) {
            $telemetry->record(
                ConversionInterface::Browser,
                ConversionOutcome::fromErrorCode($exception->errorCode),
                $inputBytes,
                $startedAt,
                $exception->diagnostic?->code,
                $context(),
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
     * Record that the browser reported a successful clipboard copy.
     *
     * The client sends nothing but the fact that it happened: neither the
     * serialized input nor the JSON result travels back. Without a session
     * identifier this cannot be joined to the conversion that produced the
     * result, and it is not a conversion, so it counts no aggregate.
     */
    #[On('result-copied')]
    public function resultCopied(Request $request, UsageEventRecorder $recorder): void
    {
        if ($this->result === null) {
            return;
        }

        $recorder->record(
            ConversionInterface::Browser,
            UsageEventType::ResultCopied,
            UsageContext::fromRequest($request, httpStatus: self::TRANSPORT_STATUS),
        );
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
