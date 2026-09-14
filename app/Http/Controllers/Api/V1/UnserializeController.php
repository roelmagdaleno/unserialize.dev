<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConversionErrorCode;
use App\Exceptions\ConversionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UnserializeRequest;
use App\Services\Serialized;
use Illuminate\Http\JsonResponse;

class UnserializeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UnserializeRequest $request): JsonResponse
    {
        try {
            $result = (new Serialized($request->string('serialized')->toString()))->convert();
        } catch (ConversionException $exception) {
            $status = $exception->errorCode === ConversionErrorCode::InputTooLarge ? 413 : 422;

            return response()->json([
                'error' => [
                    'code' => $exception->errorCode->value,
                    'message' => $exception->getMessage(),
                ],
            ], $status);
        }

        return response()->json([
            'data' => [
                'value' => $result->value,
                'format' => 'json',
            ],
            'meta' => ['retained' => false],
        ]);
    }
}
