<?php

it('renders the application title', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Unserialize Online: Convert PHP Serialized Data to JSON</title>', false);
});

it('renders an appearance dropdown with light, dark, and system options', function () {
    $this->get('/')
        ->assertSee('Preferred color scheme')
        ->assertSeeInOrder(['Light', 'Dark', 'System'])
        ->assertSee('data-theme-choice="light"', false)
        ->assertSee('data-theme-choice="dark"', false)
        ->assertSee('data-theme-choice="system"', false)
        ->assertSee('dark:bg-zinc-900', false);
});
