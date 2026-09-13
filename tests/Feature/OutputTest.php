<?php

use App\Models\Output;
use Livewire\Livewire;

it('redirects to home page when visit outputs page without id', function () {
    $this->get('/o')->assertRedirect('/');
});

it('gets the output component on output page', function () {
    $output = Output::factory()->create();

    $this->get('/o/'.$output->id)
        ->assertSeeLivewire(App\Livewire\Output::class)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false)
        ->assertDontSee('<link rel="canonical"', false);
});

it('returns noindex for a missing legacy output', function () {
    $this->get('/o/'.fake()->uuid())
        ->assertNotFound()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

it('sees the output data on output page', function () {
    $output = Output::factory()->create();

    Livewire::test(App\Livewire\Output::class, ['output' => $output])
        ->assertSee('Output: JSON')
        ->assertSee($output->created_at);
});

it('renders legacy array output records', function () {
    $output = Output::factory()->create([
        'unserialized' => "[\n    'name' => 'Chrome'\n]",
        'output_format' => 'array',
    ]);

    Livewire::test(App\Livewire\Output::class, ['output' => $output])
        ->assertSee('Output: Array')
        ->assertSee('Chrome');
});

it('escapes stored serialized data while rendering highlighted output markup', function () {
    $serializedData = 's:29:"<script>alert(\'xss\')</script>";';
    $output = Output::factory()->create([
        'serialized' => $serializedData,
        'unserialized' => '"<script>alert(\'xss\')</script>"',
    ]);

    $response = $this->get(route('outputs', $output));

    $response
        ->assertSee($serializedData)
        ->assertDontSee($serializedData, false)
        ->assertSee($output->syntax_highlighted, false);
});
