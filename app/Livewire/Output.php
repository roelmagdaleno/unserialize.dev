<?php

namespace App\Livewire;

use App\Models\Output as OutputModel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Output extends Component
{
    /**
     * The output model.
     *
     * @since 1.0.0
     */
    public OutputModel $output;

    /**
     * Mount the component.
     *
     * Set the output formats and more.
     *
     * @since 1.0.0
     */
    public function mount(OutputModel $output): void
    {
        $this->output = $output;
    }

    /**
     * Render the view.
     *
     * @since 1.0.0
     */
    public function render(): View
    {
        return view('livewire.output');
    }
}
