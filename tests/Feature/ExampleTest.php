<?php

it('renders the application title', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Unserialize - Convert your serialized data into a readable format</title>', false);
});
