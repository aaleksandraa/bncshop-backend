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

    <x-filament::section heading="Mapiranje kategorija (Stage)" class="mb-6">
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
            Ananas GET je potvrdio tačne stringove: <code>Gaming laptopi</code> (BNC 199) i
            <code>Nosači za televizor</code> (BNC 231). Stari kandidat <code>Laptopi</code> nije validan.
            Primijeni mapiranja, pa pokreni dry-run importa — live POST zahtijeva uključene catalog writes.
        </p>

        @if ($mappingRows === [])
            <p class="mb-4 text-sm text-warning-700 dark:text-warning-300">
                Nema mapiranja. Kliknite <strong>Primijeni Stage mapiranja</strong>.
            </p>
        @else
            <div class="mb-4 overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="py-2 pr-3 font-medium">ID</th>
                            <th class="py-2 pr-3 font-medium">BNC</th>
                            <th class="py-2 pr-3 font-medium">SKU</th>
                            <th class="py-2 pr-3 font-medium">Ananas category</th>
                            <th class="py-2 pr-3 font-medium">Validacija</th>
                            <th class="py-2 font-medium">Export</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mappingRows as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-3">{{ $row['id'] }}</td>
                                <td class="py-2 pr-3">{{ $row['bnc_category'] }}</td>
                                <td class="py-2 pr-3">{{ $row['products_count'] }}</td>
                                <td class="py-2 pr-3"><code>{{ $row['ananas_category'] }}</code></td>
                                <td class="py-2 pr-3">{{ $row['validation'] }}</td>
                                <td class="py-2">{{ ! empty($row['enabled']) ? 'uključeno' : 'isključeno' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button type="button" wire:click="applyValidatedMappings" wire:loading.attr="disabled">
                Primijeni Stage mapiranja
            </x-filament::button>
            <x-filament::button type="button" tag="a" :href="$this->mappingsUrl()" color="gray">
                Otvori mapiranja
            </x-filament::button>
        </div>
    </x-filament::section>

    @if (! empty($lastCatalogAction))
        <x-filament::section :heading="$lastCatalogAction['title'] ?? 'Zadnja akcija'" class="mb-6">
            <pre class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-200">{{ $lastCatalogAction['body'] ?? '' }}</pre>
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

                <div>
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Katalog (Stage)</p>
                    <div class="mb-3 max-w-xs">
                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Limit po batchu (1–100)</label>
                        <input
                            type="number"
                            min="1"
                            max="100"
                            wire:model="importLimit"
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900"
                        />
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <x-filament::button type="button" color="gray" wire:click="importDryRun" wire:loading.attr="disabled">
                            Import dry-run
                        </x-filament::button>
                        <x-filament::button
                            type="button"
                            color="warning"
                            wire:click="importLive"
                            wire:confirm="Šalje POST import na Ananas. Nastaviti?"
                            wire:loading.attr="disabled"
                        >
                            Import (live)
                        </x-filament::button>
                        <x-filament::button type="button" color="gray" wire:click="reconcileProducts" wire:loading.attr="disabled">
                            Reconcile
                        </x-filament::button>
                        <x-filament::button type="button" color="gray" wire:click="publishDryRun" wire:loading.attr="disabled">
                            Publish dry-run
                        </x-filament::button>
                        <x-filament::button
                            type="button"
                            color="warning"
                            wire:click="publishLive"
                            wire:confirm="Šalje publish job za READY_FOR_PUBLISH artikle. Nastaviti?"
                            wire:loading.attr="disabled"
                        >
                            Publish (live)
                        </x-filament::button>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Publish body je lista merchant inventory ID-eva (GET <code>id</code>: 2566378 / 2566379).
                        Job je async — Ananas šalje email; GET status treba postati PUBLISHED.
                    </p>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Akcija (discounts API)</p>
                    <div class="mb-3 grid max-w-md grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Popust % (min 5)</label>
                            <input
                                type="number"
                                min="5"
                                max="95"
                                wire:model="discountPercent"
                                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Trajanje dana (SALE max 31)</label>
                            <input
                                type="number"
                                min="1"
                                max="31"
                                wire:model="discountDays"
                                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900"
                            />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <x-filament::button type="button" color="gray" wire:click="discountDryRun" wire:loading.attr="disabled">
                            Akcija dry-run
                        </x-filament::button>
                        <x-filament::button
                            type="button"
                            color="warning"
                            wire:click="discountLive"
                            wire:confirm="Šalje POST /discounts (SALE, currency RSD po API enumu). Nastaviti?"
                            wire:loading.attr="disabled"
                        >
                            Akcija (live)
                        </x-filament::button>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Ananas: SALE ≤ 31 dan, cijena akcije ≤ 95% regularne, bez preklapanja.
                        <code>discountPriceCurrency</code> smije biti samo <code>RSD</code> — broj je isti kao import <code>basePrice</code>.
                        Ako BNC artikal već ima nižu display cijenu (≥5% off), koristi se ta.
                    </p>
                </div>
            </div>
        </x-filament::section>
    </form>
</x-filament-panels::page>
