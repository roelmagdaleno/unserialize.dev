<?php

it('explains stateless processing and legacy output retention', function () {
    $this->get(route('privacy'))
        ->assertSee('New conversions are processed in memory')
        ->assertSee('not stored in the outputs database')
        ->assertSee('Request bodies are not recorded in application logs or Nightwatch')
        ->assertSee('Legacy output links')
        ->assertSee('may remain available')
        ->assertSee('262,144 bytes');
});

it('summarizes privacy beside the converter', function () {
    $this->get(route('home'))
        ->assertSee('Your input is sent to this server for conversion')
        ->assertSee('not stored or logged')
        ->assertSee('262,144 bytes')
        ->assertSee(route('privacy'));
});
