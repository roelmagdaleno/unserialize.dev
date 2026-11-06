<?php

namespace Tests;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

/**
 * What the converter page would show after one submission, with assertions
 * phrased against that rather than against the endpoint's JSON.
 */
class ConverterResponse
{
    /**
     * The field error and panels as one HTML document.
     */
    private TestResponse $page;

    /**
     * Read the rendered panels out of the endpoint's answer.
     */
    public function __construct(public TestResponse $response)
    {
        $response->assertOk();

        $this->page = TestResponse::fromBaseResponse(response(
            e((string) $response->json('error')).$response->json('diagnostic').$response->json('result'),
        ));
    }

    /**
     * Assert the field shows an error.
     */
    public function assertHasError(): self
    {
        Assert::assertNotNull($this->response->json('error'));

        return $this;
    }

    /**
     * Assert the field shows no error.
     */
    public function assertHasNoError(): self
    {
        Assert::assertNull($this->response->json('error'));

        return $this;
    }

    /**
     * Assert the value the field holds after the submission.
     */
    public function assertFieldHolds(string $serializedData): self
    {
        Assert::assertSame($serializedData, $this->response->json('serializedData'));

        return $this;
    }

    /**
     * Assert the page shows this text.
     */
    public function assertSee(string $text): self
    {
        $this->page->assertSee($text);

        return $this;
    }

    /**
     * Assert the page does not show this text.
     */
    public function assertDontSee(string $text): self
    {
        $this->page->assertDontSee($text);

        return $this;
    }

    /**
     * Assert the page contains this markup.
     */
    public function assertSeeHtml(string $html): self
    {
        $this->page->assertSee($html, false);

        return $this;
    }

    /**
     * Assert the page does not contain this markup.
     */
    public function assertDontSeeHtml(string $html): self
    {
        $this->page->assertDontSee($html, false);

        return $this;
    }

    /**
     * The field error and panels as HTML.
     */
    public function html(): string
    {
        return (string) $this->page->getContent();
    }
}
