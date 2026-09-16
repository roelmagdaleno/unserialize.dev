<?php

namespace App\Livewire;

use App\Models\Output as OutputModel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Shows one stored conversion output on its own page.
 */
#[Layout('components.layouts.app')]
class Output extends Component
{
    /**
     * The output being displayed.
     */
    public OutputModel $output;

    /**
     * Bind the resolved output to the component.
     */
    public function mount(OutputModel $output): void
    {
        $this->output = $output;
    }

    /**
     * Render the output page.
     */
    public function render(): View
    {
        return view('livewire.output');
    }
}
