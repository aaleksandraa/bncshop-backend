<x-filament-panels::page>
    @if (empty($status['has_credentials']))
        <div class="mb-6 rounded-lg border border-warning-300 bg-warning-50 p-4 dark:border-warning-600 dark:bg-warning-950">
            <p class="text-sm font-semibold text-warning-800 dark:text-warning-200">OLX kredencijali nisu postavljeni</p>
            <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">
                Unesite korisničko ime i lozinku u sekciji <strong>API pristup</strong> ispod, ili postavite
                <code>OLX_USERNAME</code> i <code>OLX_PASSWORD</code> u <code>.env</code> fajlu.
            </p>
        </div>
    @endif

    <div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Status konekcije</p>
            <p class="text-base font-semibold capitalize">{{ $status['connection_status'] ?? 'unknown' }}</p>
            @if (! empty($status['last_error']))
                <p class="text-xs text-danger-600 dark:text-danger-400">{{ $status['last_error'] }}</p>
            @endif
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Shop nalog</p>
            <p class="text-base font-semibold">{{ $status['shop_username'] ?? '—' }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Izvor: {{ ($status['credentials_source'] ?? 'none') === 'admin' ? 'Admin panel' : (($status['credentials_source'] ?? '') === 'env' ? '.env' : 'Nije postavljeno') }}
            </p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Zadnji uspješni sync</p>
            <p class="text-base font-semibold">
                {{ isset($status['last_successful_sync_at']) && $status['last_successful_sync_at']
                    ? $status['last_successful_sync_at']->format('d.m.Y H:i')
                    : '—' }}
            </p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Zadnji job</p>
            @if (! empty($status['latest_job']))
                <p class="text-base font-semibold">
                    #{{ $status['latest_job']['id'] }} — {{ $status['latest_job']['type'] }}
                    ({{ $status['latest_job']['status'] }})
                </p>
            @else
                <p class="text-base font-semibold">—</p>
            @endif
        </x-filament::section>
    </div>

    @if (! empty($status['latest_job']['stats']))
        @php
            $stats = $status['latest_job']['stats'];
            $actions = $stats['actions'] ?? [];
            $scan = $stats['scan'] ?? [];
            $errors = $actions['errors'] ?? [];
        @endphp
        <x-filament::section heading="Rezultat zadnjeg sync-a" class="mb-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Mod</p>
                    <p class="font-semibold">{{ $stats['mode'] ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Skenirano / nepromijenjeno</p>
                    <p class="font-semibold">{{ $scan['scanned'] ?? 0 }} / {{ $scan['unchanged'] ?? 0 }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Kreirano / ažurirano</p>
                    <p class="font-semibold">{{ $actions['created'] ?? 0 }} / {{ $actions['updated'] ?? 0 }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sakriveno / preskočeno</p>
                    <p class="font-semibold">
                        {{ ($actions['hidden'] ?? 0) + ($actions['unhidden'] ?? 0) }} sakriveno
                        · {{ $actions['skipped_legacy'] ?? 0 }} legacy
                        · {{ $actions['skipped_validation'] ?? 0 }} validacija
                    </p>
                </div>
            </div>
            @if (count($errors) > 0)
                <div class="mt-4 rounded-lg border border-danger-300 bg-danger-50 p-3 dark:border-danger-700 dark:bg-danger-950">
                    <p class="text-sm font-medium text-danger-800 dark:text-danger-200">
                        Greške ({{ count($errors) }}{{ count($errors) >= 20 ? ', prikaz prvih 5' : '' }})
                    </p>
                    <ul class="mt-2 space-y-1 text-xs text-danger-700 dark:text-danger-300">
                        @foreach (array_slice($errors, 0, 5) as $error)
                            <li>#{{ $error['product_id'] ?? '?' }} ({{ $error['action'] ?? '?' }}): {{ \Illuminate\Support\Str::limit($error['message'] ?? '', 120) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
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
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Priprema (jednom)</p>
                    <div class="flex flex-wrap gap-3">
                        <x-filament::button type="button" color="gray" wire:click="discoverCategories">Osvježi OLX kategorije</x-filament::button>
                        <x-filament::button type="button" color="gray" wire:click="discoverAttributes">Osvježi OLX atribute</x-filament::button>
                        <x-filament::button type="button" color="primary" wire:click="seedMappings" wire:confirm="Učitati BNC→OLX mapiranja kategorija i atributa (15 kategorija)?">Učitaj mapiranja</x-filament::button>
                        <x-filament::button type="button" color="warning" wire:click="importExistingListings" wire:confirm="Uvesti postojeće OLX oglase kao legacy (read-only)?">Import legacy oglasa</x-filament::button>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Sync proizvoda</p>
                    <div class="flex flex-wrap gap-3">
                        <x-filament::button type="button" color="success" wire:click="runIncrementalSync">Inkrementalni sync (queue)</x-filament::button>
                        <x-filament::button type="button" color="warning" wire:click="runFullSync">Puni sync (queue)</x-filament::button>
                        <x-filament::button type="button" color="info" wire:click="runSyncNow">Pokreni sync (queue)</x-filament::button>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </form>
</x-filament-panels::page>
