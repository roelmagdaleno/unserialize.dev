<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('unserialize:'.hash('sha256', '127.0.0.1'));
});

it('renders the converter on the home page', function () {
    $this->get('/')
        ->assertSee('data-converter', false)
        ->assertDontSee('Output Format')
        ->assertDontSee('value="array"', false);
});

it('validates required and serialized input with user-visible messages', function (string $serializedData, string $message) {
    convertInBrowser($serializedData)
        ->assertHasError()
        ->assertSee($message);

    $this->assertDatabaseCount('outputs', 0);
})->with([
    'required input' => ['', 'The serialized data field is required.'],
    'invalid serialized input' => ['invalid', 'Invalid serialized data.'],
]);

it('rejects serialized input larger than 256 KiB before persistence', function () {
    $serializedData = serialize(str_repeat('a', (256 * 1024) + 1));

    convertInBrowser($serializedData)
        ->assertHasError()
        ->assertSee('The serialized data must not be greater than 262,144 bytes.');

    $this->assertDatabaseCount('outputs', 0);
});

it('displays a valid conversion without persisting it', function () {
    $serializedData = 'a:1:{s:4:"name";s:6:"Chrome";}';

    convertInBrowser($serializedData)
        ->assertSee('"name": "Chrome"')
        ->assertSee('This result is not retained by Unserialize.');

    $this->assertDatabaseCount('outputs', 0);
    $this->assertDatabaseHas('conversion_metrics', [
        'interface' => 'browser',
        'outcome' => 'success',
        'count' => 1,
    ]);
});

it('displays a converted object', function () {
    convertInBrowser('O:4:"User":2:{s:4:"name";s:3:"Ada";s:5:"email";s:15:"ada@example.com";}')
        ->assertHasNoError()
        ->assertSee('"name": "Ada"');

    $this->assertDatabaseCount('outputs', 0);
});

it('shows specific feedback for serialized objects without persistence', function () {
    convertInBrowser('C:11:"ArrayObject":0:{}')
        ->assertHasError()
        ->assertSee('Custom-serialized objects and enums are not supported.');

    $this->assertDatabaseCount('outputs', 0);
});

it('blocks the eleventh conversion attempt for an IP address', function () {
    /**
     * The assertion names an exact countdown, so the clock is frozen. Without
     * this the window opens on the first attempt and the ten that follow can
     * carry it past a whole second, leaving the message reading 59.
     */
    $this->freezeTime();

    foreach (range(1, 10) as $attempt) {
        convertInBrowser('invalid')
            ->assertHasError();
    }

    convertInBrowser('i:1;')
        ->assertHasError()
        ->assertSee('Too many conversion attempts. Please try again in 60 seconds.');

    $this->assertDatabaseCount('outputs', 0);
    $this->assertDatabaseHas('conversion_metrics', [
        'interface' => 'browser',
        'outcome' => 'rate_limited',
        'count' => 1,
    ]);
});

it('frames the broken part of the value and suggests a correction', function () {
    convertInBrowser('a:10:{s:4:"names";s:6:"Chrome";}')
        ->assertHasError()
        ->assertSee('Invalid serialized data.')
        ->assertSee('declares 4 bytes but 5 bytes precede the closing quote')
        ->assertSee('Byte 6, 11 bytes marked below')
        ->assertSee('Change `s:4:` to `s:5:`.')
        ->assertSeeHtml('role="alert"')
        ->assertSeeHtml('<mark class="diagnostic-span"><span class="sr-only">start of invalid part </span>s:4:&quot;names&quot;');

    $this->assertDatabaseCount('outputs', 0);
});

it('restores the Windows line endings a textarea removes', function () {
    $sentByTextarea = 'a:1:{s:4:"note";s:5:"a'."\n".'bc";}';

    convertInBrowser($sentByTextarea)
        ->assertHasNoError()
        ->assertSee('"note": "a\r\nbc"')
        ->assertSee('Restored the Windows line endings (CRLF) your browser removed from this value.');

    $this->assertDatabaseHas('conversion_metrics', ['interface' => 'browser', 'outcome' => 'success']);
});

it('keeps the original diagnostic when restoring line endings does not help', function () {
    convertInBrowser('a:1:{s:4:"note";s:9:"a'."\n".'b";}')
        ->assertHasError()
        ->assertDontSee('Restored the Windows line endings')
        ->assertSee('declares 9 bytes but 6 bytes precede the closing quote');
});

it('does not announce a repair for a value that converts as sent', function () {
    convertInBrowser('a:1:{s:4:"note";s:3:"a'."\n".'b";}')
        ->assertDontSee('Restored the Windows line endings');
});

it('converts a value pasted with whitespace around it', function () {
    convertInBrowser("  \n".'a:1:{s:4:"name";s:6:"Chrome";}'."\n\t ")
        ->assertHasNoError()
        ->assertFieldHolds('a:1:{s:4:"name";s:6:"Chrome";}')
        ->assertSee('"name": "Chrome"');
});

it('explains a paste mistake beside the diagnostic', function (string $serializedData, string $hint) {
    convertInBrowser($serializedData)
        ->assertHasError()
        ->assertSee('Check how it was pasted:')
        ->assertSee($hint);
})->with([
    'SQL quotes' => ["'a:1:{i:0;s:1:\"x\";}'", 'Remove the quote at each end.'],
    'escaped quotes' => ['a:1:{i:0;s:1:\"x\";}', 'Replace every \" with ".'],
    'JSON' => ['{"name": "Ada"}', 'This looks like JSON, not PHP serialized data.'],
    'base64' => [base64_encode('a:1:{i:0;s:1:"x";}'), 'This looks like base64.'],
]);

it('links a string length failure to the repair guide', function () {
    convertInBrowser('a:10:{s:4:"names";N;}')
        ->assertDontSee('Check how it was pasted:')
        ->assertSeeHtml('href="'.route('guides.broken-serialized-string').'"');
});

it('reports the line and column when the value spans several lines', function () {
    convertInBrowser("a:2:{s:7:\"one\ntwo\";N;s:3:\"abcd\";N;}")
        ->assertSee('Byte 21, line 2, column 8');
});

it('shows a window around the problem instead of the whole value', function () {
    $filler = str_repeat('a', 40_000);
    $serializedData = 'a:2:{i:0;s:40000:"'.$filler.'";i:1;s:4:"names";}';

    $component = convertInBrowser($serializedData)
        ->assertSee('Change `s:4:` to `s:5:`.')
        ->assertSee('…');

    preg_match('#<section[^>]*role="alert".*?</section>#s', $component->html(), $panel);

    expect($panel)->not->toBeEmpty()
        ->and(strlen($panel[0]))->toBeLessThan(20_000);
});

it('escapes unprintable bytes and never renders submitted markup as HTML', function () {
    $html = convertInBrowser("a:1:{s:3:\"\x00<script>alert(1)</script>\";N;}")
        ->html();

    expect(mb_check_encoding($html, 'UTF-8'))->toBeTrue()
        ->and($html)->toContain('\\x00')
        ->and($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('keeps the diagnostic excerpt away from the syntax highlighter', function () {
    $html = convertInBrowser('a:10:{s:4:"names";N;}')
        ->html();

    expect($html)->toContain('<pre tabindex="0" data-highlight="off" class="diagnostic-excerpt')
        ->and($html)->not->toContain('diagnostic-excerpt rounded-md p-4" data-lang');
});

it('shows no diagnostic panel for failures that have no byte position', function (string $serializedData) {
    convertInBrowser($serializedData)
        ->assertHasError()
        ->assertDontSeeHtml('diagnostic-excerpt');
})->with([
    'custom-serialized object' => ['C:11:"ArrayObject":0:{}'],
    'oversized input' => [serialize(str_repeat('a', (256 * 1024) + 1))],
]);

it('clears the diagnostic panel once a later conversion succeeds', function () {
    convertInBrowser('a:10:{s:4:"names";N;}')->assertSee('Suggestion:');

    convertInBrowser('a:1:{s:4:"name";s:6:"Chrome";}')
        ->assertDontSee('Suggestion:')
        ->assertSee('"name": "Chrome"')
        ->response->assertExactJson([
            'serializedData' => 'a:1:{s:4:"name";s:6:"Chrome";}',
            'error' => null,
            'diagnostic' => '',
            'result' => view('partials.conversion-result', [
                'result' => "{\n    \"name\": \"Chrome\"\n}",
                'lineEndingsRestored' => false,
            ])->render(),
        ]);
});

it('clears the result panel when a later conversion fails', function () {
    convertInBrowser('a:10:{s:4:"names";N;}')
        ->assertHasError()
        ->response->assertJsonPath('result', '');
});

it('keeps the last result when the field is empty', function () {
    convertInBrowser('   ')
        ->assertSee('The serialized data field is required.')
        ->assertFieldHolds('')
        ->response
        ->assertJsonPath('diagnostic', '')
        ->assertJsonPath('result', null);
});

it('clears the diagnostic panel and keeps the last result when the conversion is rate limited', function () {
    foreach (range(1, 10) as $attempt) {
        convertInBrowser('a:10:{s:4:"names";N;}')->assertSee('Suggestion:');
    }

    convertInBrowser(' a:10:{s:4:"names";N;}')
        ->assertDontSeeHtml('diagnostic-excerpt')
        ->assertSee('Too many conversion attempts.')
        ->assertFieldHolds(' a:10:{s:4:"names";N;}')
        ->response->assertJsonPath('result', null);
});

it('prefills the field in the page itself with an example that converts', function () {
    $html = $this->get('/')->assertOk()->getContent();

    preg_match('#<textarea[^>]*>(.*?)</textarea>#s', $html, $field);

    convertInBrowser(html_entity_decode($field[1], ENT_QUOTES))->assertHasNoError();
});

it('answers a body that is not valid UTF-8 without failing', function () {
    convertInBrowser("s:3:\"\xFF\xFE\xFD\";")->response->assertOk();
    convertInBrowser("\xFF")->assertHasError();
});

it('leaves the session and CSRF protection off the stateless endpoints', function (string $route) {
    $middleware = app('router')->gatherRouteMiddleware(app('router')->getRoutes()->getByName($route));

    expect($middleware)
        ->not->toContain(StartSession::class)
        ->not->toContain(PreventRequestForgery::class);
})->with(['conversion.convert', 'conversion.copied']);
