<section class="mt-8">
    <form wire:submit="unserialize">
        <flux:textarea
            label="Serialized Data"
            wire:model="form.serializedData"
            rows="auto"
            class="font-mono text-sm md:text-base"
            autofocus="autofocus"
        />

        <div class="mt-6 md:flex items-center justify-between">
            <flux:radio.group wire:model="form.outputFormat" label="Output Format" variant="segmented">
                @foreach($outputFormats as $format => $label)
                    <flux:radio label="{{ $label }}" value="{{ $format }}" />
                @endforeach
            </flux:radio.group>

            <flux:button type="submit" class="mt-6 md:mt-0 w-full md:w-auto" variant="primary">Unserialize</flux:button>
        </div>
    </form>
</section>
