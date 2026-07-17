<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        @if($this->canEditSettings())
            <x-filament::button type="submit">
                Sačuvaj postavke
            </x-filament::button>
        @endif
    </form>
</x-filament-panels::page>
