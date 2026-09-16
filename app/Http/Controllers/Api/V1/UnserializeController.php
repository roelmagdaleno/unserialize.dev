<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\ConversionEnvelope;
use App\Data\UsageContext;
use App\Enums\ConversionErrorCode;
use App\Enums\ConversionInterface;
use App\Enums\ConversionOutcome;
use App\Exceptions\ConversionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UnserializeRequest;
use App\Services\ConversionTelemetry;
use App\Services\Serialized;
use Illuminate\Http\JsonResponse;

class UnserializeController extends Controller
{
    /**
     * The version this route publishes.
     *
     * Telemetry reads this constant rather than a request header, so a recorded
     * version is always the one that actually served the call. The 415, 422, and
     * 429 paths sit outside this controller and read it from here for the same
     * reason.
     */
    public const string API_VERSION = 'v1';

    /**
     * Handle the incoming request.
     */
    public function __invoke(UnserializeRequest $request, ConversionTelemetry $telemetry): JsonResponse
    {
        $startedAt = hrtime(true);
        $serialized = $request->string('serialized')->toString();

        try {
            $result = new Serialized($serialized)->convert();
        } catch (ConversionException $exception) {
            $status = $exception->errorCode === ConversionErrorCode::InputTooLarge ? 413 : 422;

            $telemetry->record(
                ConversionInterface::Api,
                ConversionOutcome::fromErrorCode($exception->errorCode),
                strlen($serialized),
                $startedAt,
                $exception->diagnostic?->code,
                UsageContext::fromRequest($request, apiVersion: self::API_VERSION, httpStatus: $status),
            );

            return response()->json(ConversionEnvelope::error(
                $exception->errorCode->value,
                $exception->getMessage(),
                $exception->diagnostic?->toArray(),
            ), $status);
        }

        $telemetry->record(
            ConversionInterface::Api,
            ConversionOutcome::Success,
            strlen($serialized),
            $startedAt,
            null,
            UsageContext::fromRequest(
                $request,
                apiVersion: self::API_VERSION,
                httpStatus: 200,
                resultType: UsageContext::resultTypeFor($result->value),
            ),
        );

        return response()->json(ConversionEnvelope::success($result->value));
    }
}
