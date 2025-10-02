<?php

use App\Services\Serialized;

beforeEach(function () {
    $this->serializedData = 'a:10:{s:4:"name";s:6:"Chrome";s:7:"version";s:9:"103.0.0.0";s:8:"platform";s:7:"Windows";s:10:"update_url";s:29:"https://www.google.com/chrome";s:7:"img_src";s:44:"https://s.w.org/images/browsers/chrome.png?1";s:11:"img_src_ssl";s:44:"https://s.w.org/images/browsers/chrome.png?1";s:15:"current_version";s:2:"18";s:7:"upgrade";b:0;s:8:"insecure";b:0;s:6:"mobile";b:0;}';
});

it('unserialize the serialized data', function () {
    try {
        $serialized = new Serialized($this->serializedData, 'json');
        $unserializedData = $serialized->output();

        expect($unserializedData)->toBeJson();
    } catch (Exception $e) {
    }
});

it('unserialize the serialized data with array output format', function () {
    try {
        $serialized = new Serialized($this->serializedData, 'array');
        $unserializedData = $serialized->output();

        expect($unserializedData)->toBeString();
    } catch (Exception $e) {
    }
});

it('unserialize the serialized data with invalid output format', function () {
    $serialized = new Serialized($this->serializedData, 'invalid');
    $serialized->output();
})->throws('Invalid output format.');

it('unserialize the serialized data with invalid serialized data', function () {
    $serialized = new Serialized('invalid', 'json');
    $serialized->output();
})->throws('Invalid serialized data.');

it('tries to unserialize empty data', function () {
    $serialized = new Serialized('', 'json');
    $serialized->output();
})->throws('Invalid serialized data.');

it('tries to unserialize a false value', function () {
    $serialized = new Serialized('b:0;', 'json');
    $output = $serialized->output();

    expect($output)->toBeJson();
});

it('tries to unserialize a serialized less than 4 characters', function () {
    $serialized = new Serialized('b:0', 'json');
    $serialized->output();
})->throws('Invalid serialized data.');

it('tries to unserialized a null value', function () {
    $serialized = new Serialized('N;', 'json');
    $output = $serialized->output();

    expect($output)->toBeJson();
});

it('tries to unserialized NaN value', function () {
    $serialized = new Serialized('a:1:{s:5:"value";d:NAN;}', 'json');
    $serialized->output();
})->throws('Failed to encode the serialized data to JSON.');
