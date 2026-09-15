<?php

namespace App\Http\Requests\Api\V1;

use App\Data\UsageContext;
use App\Enums\ConversionInterface;
use App\Http\Controllers\Api\V1\UnserializeController;
use App\Services\ConversionTelemetry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UnserializeRequest extends FormRequest
{
    private int $telemetryStartedAt;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'serialized' => ['bail', 'required', 'string'],
        ];
    }

    /**
     * Reject request fields that are not part of the public contract.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['serialized']) as $unexpectedField) {
                $validator->errors()->add($unexpectedField, 'This field is not supported.');
            }
        }];
    }

    /**
     * Return the public API's stable validation envelope.
     */
    protected function failedValidation(Validator $validator): void
    {
        app(ConversionTelemetry::class)->record(
            ConversionInterface::Api,
            'validation_error',
            strlen((string) $this->input('serialized', '')),
            $this->telemetryStartedAt,
            null,
            UsageContext::fromRequest(
                $this,
                apiVersion: UnserializeController::API_VERSION,
                httpStatus: 422,
            ),
        );

        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'validation_error',
                'message' => 'The request data is invalid.',
                'details' => $validator->errors()->toArray(),
            ],
        ], 422));
    }

    protected function prepareForValidation(): void
    {
        $this->telemetryStartedAt = hrtime(true);
    }
}
