<x-filament-panels::page>
    @if (empty($status['has_credentials']))
        <div class="mb-6 rounded-lg border border-warning-300 bg-warning-50 p-4 dark:border-warning-600 dark:bg-warning-950">
            <p class="text-sm font-semibold text-warning-800 dark:text-warning-200">Ananas credentials nisu postavljeni</p>
            <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">
                Unesite Client ID i Client Secret u sekciji <strong>API pristup</strong>, ili postavite
                <code>ANANAS_CLIENT_ID</code> i <code>ANANAS_CLIENT_SECRET</code> u <code>.env</code>.
            </p>
        </div>
    @endif

    <div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Status konekcije</p>
            <p class="text-base font-semibold capitalize">{{ $status['connection_status'] ?? 'unknown' }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Okruženje</p>
            <p class="text-base font-semibold">{{ $status['environment'] ?? '—' }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Izvor credentials: {{ $status['credentials_source'] ?? 'none' }}
            </p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Catalog writes</p>
            <p class="text-base font-semibold">{{ ! empty($status['allow_catalog_writes']) ? 'enabled' : 'disabled' }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Eligibility (scoped)</p>
            <p class="text-base font-semibold">
                {{ $eligibilitySummary['eligible'] ?? 0 }} / {{ $eligibilitySummary['total_scanned'] ?? 0 }} eligible
            </p>
        </x-filament::section>
    </div>

    @if (! empty($eligibilitySummary['reasons']))
        <x-filament::section heading="Eligibility razlozi (zadnji pregled)" class="mb-6">
            <ul class="space-y-1 text-sm">
                @foreach ($eligibilitySummary['reasons'] as $reason => $count)
                    <li><code>{{ $reason }}</code>: {{ $count }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::section heading="Akcije">
            <div class="space-y-4">
                <div>
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Postavke</p>
                    <div class="flex flex-wrap gap-3">
                        <x-filament::button type="submit">Sačuvaj postavke</x-filament::button>
                        <x-filament::button type="button" color="gray" wire:click="testConnection">Test konekcije</x-filament::button>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Priprema</p>
                    <div class="flex flex-wrap gap-3">
                        <x-filament::button type="button" color="gray" wire:click="refreshProductTypes">Osvježi product types</x-filament::button>
                        <x-filament::button type="button" color="gray" wire:click="runEligibilityReport">Osvježi eligibility izvještaj</x-filament::button>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </form>
</x-filament-panels::page>
