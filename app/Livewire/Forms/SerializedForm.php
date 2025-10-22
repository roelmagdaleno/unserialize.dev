<?php

namespace App\Livewire\Forms;

use App\Enums\OutputFormats;
use App\Models\Output;
use App\Rules\SerializedRule;
use App\Services\Serialized;
use Exception;
use Illuminate\Validation\Rule;
use Livewire\Form;
use Tempest\Highlight\Highlighter;

class SerializedForm extends Form
{
    /**
     * The serialized data.
     *
     * @since 1.0.0
     *
     * @var string The serialized data.
     */
    public string $serializedData = 'a:10:{s:4:"name";s:6:"Chrome";s:7:"version";s:9:"103.0.0.0";s:8:"platform";s:7:"Windows";s:10:"update_url";s:29:"https://www.google.com/chrome";s:7:"img_src";s:44:"https://s.w.org/images/browsers/chrome.png?1";s:11:"img_src_ssl";s:44:"https://s.w.org/images/browsers/chrome.png?1";s:15:"current_version";s:2:"18";s:7:"upgrade";b:0;s:8:"insecure";b:0;s:6:"mobile";b:0;}';

    /**
     * The output format.
     *
     * @since 1.0.0
     *
     * @var string The output format.
     */
    public string $outputFormat = OutputFormats::JSON->value;

    /**
     * Submit the form.
     *
     * Output the data and save it to the database.
     *
     * @since 1.0.0
     *
     * @return Output The unserialized model.
     *
     * @throws Exception If the unserialize fails.
     */
    public function submit(): Output
    {
        $this->validate();

        $serialized = new Serialized($this->serializedData, $this->outputFormat);
        $unserializedData = $serialized->output();

        return Output::create([
            'serialized' => $this->serializedData,
            'unserialized' => $unserializedData,
            'output_format' => $this->outputFormat,
        ]);
    }

    /**
     * Get the validation rules.
     *
     * @since 1.0.0
     *
     * @return array The validation rules.
     */
    protected function rules(): array
    {
        return [
            'serializedData' => ['required', new SerializedRule],
            'outputFormat' => Rule::in(array_keys(OutputFormats::toArray())),
        ];
    }
}
