<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConversionErrorCode;
use App\Enums\ConversionInterface;
use App\Exceptions\ConversionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UnserializeRequest;
use App\Services\ConversionTelemetry;
use App\Services\Serialized;
use Illuminate\Http\JsonResponse;

class UnserializeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UnserializeRequest $request, ConversionTelemetry $telemetry): JsonResponse
    {
        $startedAt = hrtime(true);
        $serialized = $request->string('serialized')->toString();

        try {
            $result = (new Serialized($serialized))->convert();
        } catch (ConversionException $exception) {
            $telemetry->record(
                ConversionInterface::Api,
                $exception->errorCode->value,
                strlen($serialized),
                $startedAt,
                $exception->diagnostic?->code,
            );

            $status = $exception->errorCode === ConversionErrorCode::InputTooLarge ? 413 : 422;

            /**
             * The diagnostic goes under its own key rather than `details`, which
             * this envelope already uses for the validation field map. No
             * submitted bytes are returned: the caller holds the value it sent,
             * so `offset` and `length` locate the problem on their own.
             */
            $error = [
                'code' => $exception->errorCode->value,
                'message' => $exception->getMessage(),
            ];

            if ($exception->diagnostic !== null) {
                $error['diagnostic'] = $exception->diagnostic->toArray();
            }

            return response()->json(['error' => $error], $status);
        }

        $telemetry->record(ConversionInterface::Api, 'success', strlen($serialized), $startedAt);

        return response()->json([
            'data' => [
                'value' => $result->value,
                'format' => 'json',
            ],
            'meta' => ['retained' => false],
        ]);
    }
}
