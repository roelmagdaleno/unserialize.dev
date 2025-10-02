<?php

namespace App\Livewire;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Livewire\Component;

class Output extends Component
{
    /**
     * The output model.
     *
     * @since 1.0.0
     *
     * @var \App\Models\Output The output model.
     */
    public \App\Models\Output $output;

    /**
     * Mount the component.
     *
     * Set the output formats and more.
     *
     * @since 1.0.0
     *
     * @param  \App\Models\Output  $output  The output model.
     */
    public function mount(\App\Models\Output $output): void
    {
        $this->output = $output;
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
        return view('livewire.output');
    }
}
