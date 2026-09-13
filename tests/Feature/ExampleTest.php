<?php

it('renders the application title', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Unserialize - Convert your serialized data into a readable format</title>', false);
});

it('renders an appearance dropdown with light, dark, and system options', function () {
    $this->get('/')
        ->assertSee('Preferred color scheme')
        ->assertSeeInOrder(['Light', 'Dark', 'System'])
        ->assertSee('$flux.appearance = \'light\'', false)
        ->assertSee('$flux.appearance = \'dark\'', false)
        ->assertSee('$flux.appearance = \'system\'', false)
        ->assertDontSee('x-model="$flux.dark"', false)
        ->assertSee('dark:bg-zinc-900', false);
});
