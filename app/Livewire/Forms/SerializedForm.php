<?php

namespace App\Livewire\Forms;

use App\Data\ConversionResult;
use App\Services\Serialized;
use Livewire\Form;

class SerializedForm extends Form
{
    /**
     * The serialized data.
     *
     * @since 1.0.0
     */
    public string $serializedData = 'a:10:{s:4:"name";s:6:"Chrome";s:7:"version";s:9:"103.0.0.0";s:8:"platform";s:7:"Windows";s:10:"update_url";s:29:"https://www.google.com/chrome";s:7:"img_src";s:44:"https://s.w.org/images/browsers/chrome.png?1";s:11:"img_src_ssl";s:44:"https://s.w.org/images/browsers/chrome.png?1";s:15:"current_version";s:2:"18";s:7:"upgrade";b:0;s:8:"insecure";b:0;s:6:"mobile";b:0;}';

    /**
     * Submit the form.
     *
     * @since 1.0.0
     */
    public function submit(): ConversionResult
    {
        $this->validate();

        return new Serialized($this->serializedData)->convert();
    }

    /**
     * Get the validation rules.
     *
     * @since 1.0.0
     */
    protected function rules(): array
    {
        return [
            'serializedData' => ['bail', 'required', 'string'],
        ];
    }
}
