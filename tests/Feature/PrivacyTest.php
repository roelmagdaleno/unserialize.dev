<?php

it('explains stateless processing and legacy output retention', function () {
    $this->get(route('privacy'))
        ->assertSee('New conversions are processed in memory')
        ->assertSee('not stored in the outputs database')
        ->assertSee('Request bodies are not recorded in application logs or Nightwatch')
        ->assertSee('a diagnostic category')
        ->assertSee('not submitted serialized values, byte offsets, or converted content')
        ->assertSee('Technical usage metadata')
        ->assertSee('the browser or client User-Agent string, the request URL, and the referring URL')
        ->assertSee('This is not anonymous data')
        ->assertSee('deleted automatically after 30 days')
        ->assertSee('The database is not backed up')
        ->assertSee('never sent to Google Analytics or any other third-party analytics provider')
        ->assertSee('Legacy output links')
        ->assertSee('may remain available')
        ->assertSee('262,144 bytes');
});

it('states the retention window the application is configured for', function () {
    config()->set('telemetry.retention_days', 7);

    $this->get(route('privacy'))->assertSee('deleted automatically after 7 days');
});

it('does not call retained usage metadata anonymous', function () {
    $this->get(route('privacy'))->assertDontSee('anonymous usage');
});

it('summarizes privacy beside the converter', function () {
    $this->get(route('home'))
        ->assertSee('Your input is processed in memory, not stored or logged')
        ->assertSee('262,144 bytes')
        ->assertSee(route('privacy'));
});
