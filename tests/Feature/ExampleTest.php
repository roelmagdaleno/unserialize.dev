<?php

it('renders the application title', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Unserialize - Convert your serialized data into a readable format</title>', false);
});

it('renders a dark mode toggle', function () {
    $this->get('/')
        ->assertSee('Dark mode')
        ->assertSee('x-model="$flux.dark"', false)
        ->assertSee('dark:bg-zinc-900', false);
});
