<?php

namespace Database\Factories;

use App\Models\Output;
use App\Services\Serialized;
use Illuminate\Database\Eloquent\Factories\Factory;

class OutputFactory extends Factory
{
    protected $model = Output::class;

    public function definition(): array
    {
        $serializedData = 'a:10:{s:4:"name";s:6:"Chrome";s:7:"version";s:9:"103.0.0.0";s:8:"platform";s:7:"Windows";s:10:"update_url";s:29:"https://www.google.com/chrome";s:7:"img_src";s:44:"https://s.w.org/images/browsers/chrome.png?1";s:11:"img_src_ssl";s:44:"https://s.w.org/images/browsers/chrome.png?1";s:15:"current_version";s:2:"18";s:7:"upgrade";b:0;s:8:"insecure";b:0;s:6:"mobile";b:0;}';
        $outputFormat = 'json';

        $serialized = new Serialized($serializedData, $outputFormat);
        $unserializedData = $serialized->output();

        return [
            'serialized' => $serializedData,
            'unserialized' => $unserializedData,
            'output_format' => $outputFormat,
        ];
    }
}
