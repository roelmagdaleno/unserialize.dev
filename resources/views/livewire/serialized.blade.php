<section class="mt-8">
    <form wire:submit="unserialize">
        <flux:textarea
            label="Serialized Data"
            wire:model="form.serializedData"
            rows="auto"
            class="font-mono text-sm md:text-base"
            autofocus="autofocus"
        />

        <div class="mt-6 flex justify-end">
            <flux:button type="submit" class="w-full md:w-auto" variant="primary">Unserialize</flux:button>
        </div>
    </form>
</section>
