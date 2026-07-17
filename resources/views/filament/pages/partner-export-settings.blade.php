<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit">
                Sačuvaj postavke
            </x-filament::button>

            <x-filament::button type="button" color="warning" wire:click="regenerateApiKey">
                Generiši novi API ključ
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
