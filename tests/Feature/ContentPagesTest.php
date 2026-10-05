<?php

use App\Data\FrequentlyAskedQuestions;
use App\Exceptions\ConversionException;
use App\Services\DiagnosticPresenter;
use App\Services\Serialized;

it('publishes the complete converter guide on the home page', function () {
    $this->get(route('home'))
        ->assertSee('How to convert PHP serialized data')
        ->assertSee('PHP serialization format')
        ->assertSee('Why I built Unserialize')
        ->assertSee('Working with WordPress serialized data')
        ->assertSee('Security and privacy')
        ->assertSee('allowed_classes')
        ->assertSee('Back up the database before making changes')
        ->assertSee('https://www.php.net/manual/en/function.serialize.php', false)
        ->assertSee('a:2:{s:4:"name";s:6:"Chrome";s:6:"active";b:1;}', false)
        ->assertSee('"active": true', false);
});

it('keeps published examples aligned with the conversion service', function (string $serializedData, mixed $expectedValue) {
    expect((new Serialized($serializedData))->convert()->value)->toBe($expectedValue);
})->with([
    'homepage example' => ['a:2:{s:4:"name";s:6:"Chrome";s:6:"active";b:1;}', ['name' => 'Chrome', 'active' => true]],
    'WordPress example' => ['a:2:{s:10:"show_title";b:1;s:14:"posts_per_page";i:10;}', ['show_title' => true, 'posts_per_page' => 10]],
    'reference example' => ['a:2:{i:0;s:1:"x";i:1;R:2;}', ['x', 'x']],
]);

it('converts the object example the home page publishes', function () {
    expect((new Serialized('O:4:"User":1:{s:4:"name";s:3:"Ada";}'))->output())
        ->toBe("{\n    \"name\": \"Ada\"\n}");
});

it('publishes developer instructions for both stateless interfaces', function () {
    $this->get(route('developers'))
        ->assertOk()
        ->assertSee('API and MCP developer guide')
        ->assertSee(url('/api/v1/unserialize'))
        ->assertSee(url('/mcp/unserialize'))
        ->assertSee('convert_php_serialized_data')
        ->assertSee('262,144 input bytes')
        ->assertSee('10 requests per minute')
        ->assertSee('error.diagnostic')
        ->assertSee('never contain bytes from the submitted value')
        ->assertSee('never rewrites the submitted value')
        ->assertSee(route('openapi'))
        ->assertSee(route('privacy'));
});

it('highlights the developer guide code blocks with Shiki', function () {
    $this->get(route('developers'))
        ->assertOk()
        ->assertSee('data-lang="bash"', false)
        ->assertSee('data-lang="json"', false);
});

it('shows the online converter tagline on every public page', function (string $routeName) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertSee('Unserialize PHP data online and convert it to clean, readable JSON.');
})->with(['home', 'guides.wordpress-serialized-data', 'guides.broken-serialized-string', 'privacy', 'developers']);

it('gives every public page one H1 naming its own topic', function (string $routeName, string $heading) {
    $html = $this->get(route($routeName))->assertOk()->getContent();

    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toMatch('/<h1[^>]*>\s*'.preg_quote($heading, '/').'/');
})->with([
    'home' => ['home', 'Unserialize PHP data online'],
    'WordPress guide' => ['guides.wordpress-serialized-data', 'WordPress serialized data'],
    'broken string guide' => ['guides.broken-serialized-string', 'Fix a broken PHP serialized string'],
    'privacy' => ['privacy', 'Privacy and retention'],
    'developers' => ['developers', 'API and MCP developer guide'],
]);

it('answers the frequently asked questions in expandable details on the home page', function () {
    $questions = (new FrequentlyAskedQuestions)->all();

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('Frequently asked questions');

    foreach ($questions as $question) {
        $response->assertSee($question['question'])->assertSee($question['html'], false);
    }

    expect(substr_count($response->getContent(), '<details'))->toBe(count($questions));
});

it('renders frequently asked answers as html without raw markdown', function () {
    $answer = (new FrequentlyAskedQuestions)->all()[0]['html'];

    expect($answer)
        ->toStartWith('<p>')
        ->toContain('<code>serialize()</code>')
        ->not->toContain('`');
});

it('includes the frequently asked questions in the markdown home page', function () {
    $response = $this->get(route('home'), ['Accept' => 'text/markdown'])
        ->assertOk()
        ->assertSee('## Frequently asked questions', false);

    foreach ((new FrequentlyAskedQuestions)->all() as $question) {
        $response->assertSee('### '.$question['question'], false)->assertSee($question['markdown'], false);
    }
});

it('publishes each guide with its sections and a link to the converter', function (string $routeName, array $headings) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertSeeInOrder($headings)
        ->assertSee('href="'.route('home').'"', false)
        ->assertSee('"@type":"TechArticle"', false);
})->with([
    'wordpress guide' => ['guides.wordpress-serialized-data', [
        'Where WordPress stores serialized data',
        'Read a serialized value',
        'Read serialized data as a PHP array',
        'Convert it to JSON',
        'Edit serialized data safely',
        'Why a plain search and replace breaks it',
    ]],
    'broken string guide' => ['guides.broken-serialized-string', [
        'A string length changed after a search and replace',
        'Characters were counted instead of bytes',
        'The value was truncated',
        'Prevent it next time',
    ]],
]);

it('keeps the wordpress guide example aligned with the conversion service', function () {
    $serializedData = 'a:3:{s:10:"show_title";b:1;s:14:"posts_per_page";i:10;s:8:"home_url";s:19:"https://example.com";}';

    expect((new Serialized($serializedData))->convert()->value)
        ->toBe(['show_title' => true, 'posts_per_page' => 10, 'home_url' => 'https://example.com']);

    $this->get(route('guides.wordpress-serialized-data'))->assertSee($serializedData);
});

it('quotes the converter diagnostic for every broken example in the guide', function (string $serializedData) {
    try {
        (new Serialized($serializedData))->convert();
        $this->fail('The broken example converted.');
    } catch (ConversionException $exception) {
        $diagnostic = app(DiagnosticPresenter::class)->present($exception->diagnostic, $serializedData);
    }

    $response = $this->get(route('guides.broken-serialized-string'))
        ->assertSee($serializedData)
        ->assertSee($diagnostic['message']);

    if ($diagnostic['suggestion'] !== null) {
        $response->assertSee(preg_replace('/`([^`]+)`/', '<code>$1</code>', $diagnostic['suggestion']), false);
    }
})->with([
    'length changed by a search and replace' => 'a:2:{s:8:"home_url";s:19:"https://staging.example.com";s:10:"show_title";b:1;}',
    'characters counted instead of bytes' => 'a:1:{s:4:"city";s:9:"Querétaro";}',
    'truncated value' => 'a:2:{i:0;s:3:"php";i:1;s:9:"wordpress";',
]);

it('redirects the retired wordpress guide address to its replacement', function () {
    $this->get('/guides/wordpress')
        ->assertStatus(301)
        ->assertRedirect(route('guides.wordpress-serialized-data'));
});
