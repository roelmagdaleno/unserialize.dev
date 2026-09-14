<?php

use App\Livewire\Serialized;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    RateLimiter::clear('unserialize:'.hash('sha256', '127.0.0.1'));
});

it('renders the converter on the home page', function () {
    $this->get('/')
        ->assertSeeLivewire(Serialized::class)
        ->assertDontSee('Output Format')
        ->assertDontSee('value="array"', false);
});

it('validates required and serialized input with user-visible messages', function (string $serializedData, string $message) {
    Livewire::test(Serialized::class)
        ->set('form.serializedData', $serializedData)
        ->call('unserialize')
        ->assertHasErrors('form.serializedData')
        ->assertSee($message);

    $this->assertDatabaseCount('outputs', 0);
})->with([
    'required input' => ['', 'The serialized data field is required.'],
    'invalid serialized input' => ['invalid', 'Invalid serialized data.'],
]);

it('rejects serialized input larger than 256 KiB before persistence', function () {
    $serializedData = serialize(str_repeat('a', (256 * 1024) + 1));

    Livewire::test(Serialized::class)
        ->set('form.serializedData', $serializedData)
        ->call('unserialize')
        ->assertHasErrors('form.serializedData')
        ->assertSee('The serialized data must not be greater than 262,144 bytes.');

    $this->assertDatabaseCount('outputs', 0);
});

it('displays a valid conversion without persisting it', function () {
    $serializedData = 'a:1:{s:4:"name";s:6:"Chrome";}';

    Livewire::test(Serialized::class)
        ->set('form.serializedData', $serializedData)
        ->call('unserialize')
        ->assertNoRedirect()
        ->assertSee('"name": "Chrome"')
        ->assertSee('This result is not retained by Unserialize.');

    $this->assertDatabaseCount('outputs', 0);
});

it('shows specific feedback for serialized objects without persistence', function () {
    Livewire::test(Serialized::class)
        ->set('form.serializedData', 'O:8:"stdClass":0:{}')
        ->call('unserialize')
        ->assertHasErrors('form.serializedData')
        ->assertSee('Serialized objects are not supported.');

    $this->assertDatabaseCount('outputs', 0);
});

it('blocks the eleventh conversion attempt for an IP address', function () {
    foreach (range(1, 10) as $attempt) {
        Livewire::test(Serialized::class)
            ->set('form.serializedData', 'invalid')
            ->call('unserialize')
            ->assertHasErrors('form.serializedData');
    }

    Livewire::test(Serialized::class)
        ->set('form.serializedData', 'i:1;')
        ->call('unserialize')
        ->assertHasErrors('form.serializedData')
        ->assertSee('Too many conversion attempts. Please try again in 60 seconds.');

    $this->assertDatabaseCount('outputs', 0);
});

it('frames the broken part of the value and suggests a correction', function () {
    Livewire::test(Serialized::class)
        ->set('form.serializedData', 'a:10:{s:4:"names";s:6:"Chrome";}')
        ->call('unserialize')
        ->assertHasErrors('form.serializedData')
        ->assertSee('Invalid serialized data.')
        ->assertSee('declares 4 bytes but 5 bytes precede the closing quote')
        ->assertSee('Byte 6, 11 bytes marked below')
        ->assertSee('Change `s:4:` to `s:5:`.')
        ->assertSeeHtml('role="alert"')
        ->assertSeeHtml('<mark class="diagnostic-span"><span class="sr-only">start of invalid part </span>s:4:&quot;names&quot;');

    $this->assertDatabaseCount('outputs', 0);
});

it('reports the line and column when the value spans several lines', function () {
    Livewire::test(Serialized::class)
        ->set('form.serializedData', "a:2:{s:7:\"one\ntwo\";N;s:3:\"abcd\";N;}")
        ->call('unserialize')
        ->assertSee('Byte 21, line 2, column 8');
});

it('shows a window around the problem instead of the whole value', function () {
    $filler = str_repeat('a', 40_000);
    $serializedData = 'a:2:{i:0;s:40000:"'.$filler.'";i:1;s:4:"names";}';

    $component = Livewire::test(Serialized::class)
        ->set('form.serializedData', $serializedData)
        ->call('unserialize')
        ->assertSee('Change `s:4:` to `s:5:`.')
        ->assertSee('…');

    expect(strlen($component->html()))->toBeLessThan(20_000);
});

it('escapes unprintable bytes and never renders submitted markup as HTML', function () {
    $html = Livewire::test(Serialized::class)
        ->set('form.serializedData', "a:1:{s:3:\"\x00<script>alert(1)</script>\";N;}")
        ->call('unserialize')
        ->html();

    expect(mb_check_encoding($html, 'UTF-8'))->toBeTrue()
        ->and($html)->toContain('\\x00')
        ->and($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('keeps the diagnostic excerpt away from the syntax highlighter', function () {
    $html = Livewire::test(Serialized::class)
        ->set('form.serializedData', 'a:10:{s:4:"names";N;}')
        ->call('unserialize')
        ->html();

    expect($html)->toContain('<pre tabindex="0" data-highlight="off" class="diagnostic-excerpt')
        ->and($html)->not->toContain('diagnostic-excerpt rounded-md p-4" data-lang');
});

it('shows no diagnostic panel for failures that have no byte position', function (string $serializedData) {
    Livewire::test(Serialized::class)
        ->set('form.serializedData', $serializedData)
        ->call('unserialize')
        ->assertHasErrors('form.serializedData')
        ->assertSet('diagnostic', null)
        ->assertDontSeeHtml('diagnostic-excerpt');
})->with([
    'serialized object' => ['O:8:"stdClass":0:{}'],
    'oversized input' => [serialize(str_repeat('a', (256 * 1024) + 1))],
]);

it('clears the diagnostic panel once a later conversion succeeds', function () {
    Livewire::test(Serialized::class)
        ->set('form.serializedData', 'a:10:{s:4:"names";N;}')
        ->call('unserialize')
        ->assertSee('Suggestion:')
        ->set('form.serializedData', 'a:1:{s:4:"name";s:6:"Chrome";}')
        ->call('unserialize')
        ->assertSet('diagnostic', null)
        ->assertDontSee('Suggestion:')
        ->assertSee('"name": "Chrome"');
});

it('clears the diagnostic panel when the conversion is rate limited', function () {
    foreach (range(1, 10) as $attempt) {
        Livewire::test(Serialized::class)
            ->set('form.serializedData', 'a:10:{s:4:"names";N;}')
            ->call('unserialize')
            ->assertSee('Suggestion:');
    }

    Livewire::test(Serialized::class)
        ->set('form.serializedData', 'a:10:{s:4:"names";N;}')
        ->call('unserialize')
        ->assertSet('diagnostic', null)
        ->assertDontSeeHtml('diagnostic-excerpt')
        ->assertSee('Too many conversion attempts.');
});
