<?php

namespace App\Livewire;

use App\Enums\OutputFormats;
use App\Livewire\Forms\SerializedForm;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Serialized extends Component
{
    /**
     * The serialized form.
     *
     * @since 1.0.0
     *
     * @var SerializedForm The serialized form.
     */
    public SerializedForm $form;

    /**
     * The output formats.
     *
     * @since 1.0.0
     *
     * @var array The output formats.
     */
    #[Locked]
    public array $outputFormats = [];

    /**
     * Mount the component.
     *
     * Set the output formats and more.
     *
     * @since 1.0.0
     */
    public function mount(): void
    {
        $this->outputFormats = OutputFormats::toArray();
    }

    /**
     * Start the unserialize process.
     *
     * @since 1.0.0
     *
     * @throws Exception If unserialize fails.
     */
    public function unserialize(): void
    {
        try {
            $output = $this->form->submit();
            $this->redirectRoute('outputs', $output);
        } catch (Exception $e) {
            $this->addError('form.serializedData', $e->getMessage());
        }
    }

    /**
     * Render the view.
     *
     * @since 1.0.0
     *
     * @return Application|Factory|View|\Illuminate\View\View The rendered view.
     */
    public function render(): Application|Factory|View|\Illuminate\View\View
    {
        return view('livewire.serialized');
    }
}
