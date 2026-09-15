<?php

use App\Services\UsageMetadataNormalizer;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->normalizer = new UsageMetadataNormalizer;
});

it('keeps a complete user agent unchanged', function () {
    $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/141.0 Safari/537.36';

    expect($this->normalizer->userAgent($userAgent))->toBe($userAgent);
});

it('removes control characters from a user agent', function () {
    expect($this->normalizer->userAgent("curl/8.7.1\r\nX-Injected: yes"))
        ->toBe('curl/8.7.1X-Injected: yes');
});

it('returns null for a user agent that is only control characters', function () {
    expect($this->normalizer->userAgent("\x00\x07\x1b"))->toBeNull();
});

it('returns null for a user agent holding invalid utf-8 bytes', function () {
    expect($this->normalizer->userAgent("curl/8.7.1 \xC3\x28"))->toBeNull();
});

it('returns null for a user agent longer than the configured cap', function () {
    config()->set('telemetry.max_user_agent_bytes', 32);

    expect($this->normalizer->userAgent(str_repeat('a', 33)))->toBeNull()
        ->and($this->normalizer->userAgent(str_repeat('a', 32)))->toBe(str_repeat('a', 32));
});

it('returns null when no user agent was sent', function () {
    expect($this->normalizer->userAgent(null))->toBeNull();
});

it('keeps a url without credentials or sensitive keys unchanged', function () {
    $url = 'https://unserialize.dev/api/v1/unserialize?page=2&sort=-created_at';

    expect($this->normalizer->url($url))->toBe($url);
});

it('removes user and password credentials from a url', function () {
    expect($this->normalizer->url('https://alice:s3cret@unserialize.dev/api/v1/unserialize'))
        ->toBe('https://unserialize.dev/api/v1/unserialize');
});

it('redacts a sensitive query value while preserving every other value', function (string $key) {
    expect($this->normalizer->url("https://unserialize.dev/?{$key}=abc123&page=2"))
        ->toBe("https://unserialize.dev/?{$key}=[redacted]&page=2");
})->with(['token', 'access_token', 'api_key', 'key', 'secret', 'password', 'code', 'authorization']);

it('redacts a sensitive query key regardless of its case', function () {
    expect($this->normalizer->url('https://unserialize.dev/?ToKeN=abc123&Page=2'))
        ->toBe('https://unserialize.dev/?ToKeN=[redacted]&Page=2');
});

it('redacts credentials and a sensitive value in the same url', function () {
    expect($this->normalizer->url('https://alice:s3cret@unserialize.dev/o/abc?token=abc123#frag'))
        ->toBe('https://unserialize.dev/o/abc?token=[redacted]#frag');
});

it('leaves a fragment and an unknown query key intact', function () {
    $url = 'https://unserialize.dev/developers?utm_source=news#mcp';

    expect($this->normalizer->url($url))->toBe($url);
});

it('returns null for a url holding invalid utf-8 bytes', function () {
    expect($this->normalizer->url("https://unserialize.dev/?q=\xC3\x28"))->toBeNull();
});

it('removes control characters from a url', function () {
    expect($this->normalizer->url("https://unserialize.dev/\nprivacy"))
        ->toBe('https://unserialize.dev/privacy');
});

it('returns null for a url longer than the configured cap', function () {
    config()->set('telemetry.max_url_bytes', 48);

    expect($this->normalizer->url('https://unserialize.dev/?q='.str_repeat('a', 64)))->toBeNull();
});

it('returns null when a redacted url still exceeds the configured cap', function () {
    config()->set('telemetry.max_url_bytes', 40);

    expect($this->normalizer->url('https://unserialize.dev/?token='.str_repeat('a', 8)))->toBeNull();
});

it('returns null when no url was sent', function () {
    expect($this->normalizer->url(null))->toBeNull();
});
