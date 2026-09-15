<?php

namespace App\Data;

use Illuminate\Http\Request;

/**
 * The technology metadata a usage event is allowed to carry.
 *
 * This is the field allowlist, expressed as a type. A `Request`, a header bag,
 * or a free-form array never reaches `UsageEventRecorder`; only these nine
 * nullable scalars do. That is what makes the privacy boundary checkable by
 * reading one constructor rather than auditing every call site.
 */
readonly class UsageContext
{
    public function __construct(
        public ?string $userAgent = null,
        public ?string $requestUrl = null,
        public ?string $referrerUrl = null,
        public ?string $apiVersion = null,
        public ?int $httpStatus = null,
        public ?string $resultType = null,
        public ?string $mcpTool = null,
        public ?string $mcpTransport = null,
        public ?string $mcpProtocolVersion = null,
    ) {}

    /**
     * Read the three untrusted values an HTTP request can contribute.
     *
     * The URL is the one the server observed, never a value taken from the
     * request body. Nothing else is read: no IP address, no cookie, no
     * authorization header, and no request identifier.
     */
    public static function fromRequest(
        Request $request,
        ?string $apiVersion = null,
        ?int $httpStatus = null,
        ?string $resultType = null,
        ?string $mcpTool = null,
        ?string $mcpTransport = null,
    ): self {
        return new self(
            userAgent: $request->userAgent(),
            requestUrl: $request->fullUrl(),
            referrerUrl: $request->headers->get('referer'),
            apiVersion: $apiVersion,
            httpStatus: $httpStatus,
            resultType: $resultType,
            mcpTool: $mcpTool,
            mcpTransport: $mcpTransport,
        );
    }

    /**
     * Classify a converted root value as one bounded result type.
     *
     * Only the shape of the root value is recorded. Its contents, length, and
     * key names are conversion content and never leave the request.
     */
    public static function resultTypeFor(mixed $value): string
    {
        return match (true) {
            is_array($value) => array_is_list($value) ? 'array' : 'object_array',
            is_string($value) => 'string',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_bool($value) => 'boolean',
            default => 'null',
        };
    }
}
