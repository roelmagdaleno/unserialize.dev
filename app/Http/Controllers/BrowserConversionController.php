<?php

namespace App\Http\Controllers;

use App\Data\UsageContext;
use App\Enums\ConversionInterface;
use App\Enums\ConversionOutcome;
use App\Enums\InputTrait;
use App\Enums\UsageEventType;
use App\Exceptions\ConversionException;
use App\Services\BrowserConverter;
use App\Services\ConversionRateLimiter;
use App\Services\ConversionTelemetry;
use App\Services\DiagnosticPresenter;
use App\Services\InputTraitDetector;
use App\Services\UsageEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The home page converter's endpoints: converts a payload, explains a failure,
 * and records the attempt.
 */
class BrowserConversionController extends Controller
{
    /**
     * The status a conversion attempt answers with.
     *
     * A functional failure is reported inside a successful response, so this is
     * the transport result only. The event's `outcome` is what says whether the
     * conversion itself succeeded.
     */
    private const int TRANSPORT_STATUS = 200;

    /**
     * Convert the submitted payload, or explain why it could not be converted.
     *
     * The payload is the raw request body, so input middleware such as
     * `TrimStrings` never rewrites the bytes the person submitted.
     */
    public function convert(
        Request $request,
        BrowserConverter $converter,
        ConversionTelemetry $telemetry,
        DiagnosticPresenter $presenter,
        ConversionRateLimiter $limiter,
        InputTraitDetector $detector,
    ): JsonResponse {
        $submitted = $request->getContent();
        $startedAt = hrtime(true);
        $inputBytes = strlen($submitted);
        $inputTraits = $detector->detect($submitted);
        $rateLimitKey = 'unserialize:'.$limiter->key($request);

        /**
         * A browser conversion is an XHR, so the observed request URL is this
         * endpoint and the page that hosted it arrives as the referrer. Neither
         * is inferred from the submitted payload.
         */
        $context = fn (?string $resultType = null): UsageContext => UsageContext::fromRequest(
            $request,
            httpStatus: self::TRANSPORT_STATUS,
            resultType: $resultType,
        );

        if (RateLimiter::tooManyAttempts($rateLimitKey, $limiter->attemptsPerMinute())) {
            $limiter->reject(ConversionInterface::Browser, $inputBytes, $startedAt, $context());

            $seconds = RateLimiter::availableIn($rateLimitKey);

            return $this->respond($submitted, error: "Too many conversion attempts. Please try again in $seconds seconds.");
        }

        RateLimiter::hit($rateLimitKey);

        /**
         * Whitespace around the value is removed, and the field shows the trimmed
         * value, so a diagnostic's byte offset indexes what the person sees. A
         * serialized value never starts or ends with whitespace.
         */
        $serializedData = trim($submitted);

        try {
            $conversion = $converter->convert($serializedData);

            $telemetry->record(
                ConversionInterface::Browser,
                ConversionOutcome::Success,
                $inputBytes,
                $startedAt,
                null,
                $context(UsageContext::resultTypeFor($conversion->result->value)),
                $conversion->lineEndingsRestored ? [...$inputTraits, InputTrait::LineEndingsRestored] : $inputTraits,
            );

            return $this->respond($serializedData, result: view('partials.conversion-result', [
                'result' => $conversion->result->json,
                'lineEndingsRestored' => $conversion->lineEndingsRestored,
            ])->render());
        } catch (ValidationException $exception) {
            $telemetry->record(
                ConversionInterface::Browser,
                ConversionOutcome::ValidationError,
                $inputBytes,
                $startedAt,
                null,
                $context(),
            );

            return $this->respond($serializedData, error: $exception->validator->errors()->first('serializedData'));
        } catch (ConversionException $exception) {
            $telemetry->record(
                ConversionInterface::Browser,
                ConversionOutcome::fromErrorCode($exception->errorCode),
                $inputBytes,
                $startedAt,
                $exception->diagnostic?->code,
                $context(),
                $inputTraits,
            );

            $diagnostic = $exception->diagnostic === null
                ? ''
                : view('partials.conversion-diagnostic', [
                    'diagnostic' => $presenter->present($exception->diagnostic, $serializedData),
                    'hints' => array_values(array_filter(array_map(
                        static fn (InputTrait $trait): ?string => $trait->hint(),
                        $inputTraits,
                    ))),
                ])->render();

            return $this->respond($serializedData, error: $exception->getMessage(), diagnostic: $diagnostic, result: '');
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
    public function copied(Request $request, UsageEventRecorder $recorder): Response
    {
        $recorder->record(
            ConversionInterface::Browser,
            UsageEventType::ResultCopied,
            UsageContext::fromRequest($request, httpStatus: self::TRANSPORT_STATUS),
        );

        return response('', self::TRANSPORT_STATUS);
    }

    /**
     * Answer with what the page should show after this attempt.
     *
     * A `null` result tells the page to keep the JSON it already shows, because
     * the attempt never reached a conversion. Bytes that are not valid UTF-8 are
     * substituted because the page's field cannot hold them anyway.
     *
     * @param  string  $serializedData  The value the field should now hold.
     * @param  string|null  $error  The message shown under the field.
     * @param  string  $diagnostic  The rendered diagnostic panel, empty for none.
     * @param  string|null  $result  The rendered result panel, empty to clear it.
     */
    private function respond(string $serializedData, ?string $error = null, string $diagnostic = '', ?string $result = null): JsonResponse
    {
        return response()->json([
            'serializedData' => $serializedData,
            'error' => $error,
            'diagnostic' => $diagnostic,
            'result' => $result,
        ], self::TRANSPORT_STATUS, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
