<x-filament-panels::page>
    <form wire:submit="sendNotification" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" wire:confirm="Poslati obavijest aktivnim B2B kupcima za odabrane proizvode?">
            Pošalji obavijest
        </x-filament::button>
    </form>
</x-filament-panels::page>
