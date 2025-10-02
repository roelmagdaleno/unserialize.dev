<?php

use App\Models\Output;
use Livewire\Livewire;

it('redirects to home page when visit outputs page without id', function () {
    $this->get('/o')->assertRedirect('/');
});

it('gets the output component on output page', function () {
    $output = Output::factory()->create();

    $this->get('/o/'.$output->id)
        ->assertSeeLivewire(\App\Livewire\Output::class);
});

it('sees the output data on output page', function () {
    $output = Output::factory()->create();

    Livewire::test(\App\Livewire\Output::class, ['output' => $output])
        ->assertSee('Output: JSON')
        ->assertSee($output->created_at);
});
