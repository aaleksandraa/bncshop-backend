<x-filament-panels::page>
    @if (filled($pageLoadError))
        <div class="mb-6 rounded-lg border border-danger-300 bg-danger-50 p-4 dark:border-danger-600 dark:bg-danger-950">
            <p class="text-sm font-semibold text-danger-800 dark:text-danger-200">Dio stranice nije učitan</p>
            <p class="mt-1 text-sm text-danger-700 dark:text-danger-300">{{ $pageLoadError }}</p>
        </div>
    @endif

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
            @if ($eligibilityLoaded)
                <p class="text-base font-semibold">
                    {{ $eligibilitySummary['eligible'] ?? 0 }} / {{ $eligibilitySummary['total_scanned'] ?? 0 }} eligible
                </p>
            @else
                <p class="text-base font-semibold">Nije učitano</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Pregled kataloga se pokreće tek na „Osvježi eligibility izvještaj“.</p>
            @endif
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

    <div @if ($dailyJobsActive) wire:poll.5s="refreshDailyJobsUi" @endif>
        <x-filament::section heading="Dnevni poslovi (Ananas ritam)" class="mb-6">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                Master prekidač i limit importa su u sekciji <strong>Export</strong> (Sačuvaj postavke).
                Automatski cron poštuje export, catalog writes i probe po okruženju.
            </p>

            <div class="mb-4 max-w-xs">
                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Dnevni limit importa (08:00)</label>
                <div class="flex gap-2">
                    <input
                        type="number"
                        min="1"
                        max="5000"
                        wire:model="importDailyLimit"
                        class="block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900"
                    />
                    <x-filament::button type="button" color="gray" wire:click="saveDailyImportLimit">Sačuvaj</x-filament::button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[48rem] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="py-2 pr-3 font-medium">Posao</th>
                            <th class="py-2 pr-3 font-medium">Termin</th>
                            <th class="py-2 pr-3 font-medium">Automatski</th>
                            <th class="py-2 pr-3 font-medium">Zadnji ishod</th>
                            <th class="py-2 font-medium">Akcije</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dailyJobDefinitions as $job)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-3 font-medium">{{ $job['label'] }}</td>
                                <td class="py-2 pr-3"><code>{{ $job['schedule'] }}</code></td>
                                <td class="py-2 pr-3">
                                    <x-filament::button
                                        type="button"
                                        size="xs"
                                        color="gray"
                                        wire:click="toggleDailyJobAuto('{{ $job['key'] }}')"
                                    >
                                        {{ ! empty($job['auto']) ? 'uključeno' : 'isključeno' }}
                                    </x-filament::button>
                                </td>
                                <td class="py-2 pr-3 text-xs">
                                    @if (! empty($job['latest']))
                                        <div>{{ $job['latest']['status'] }} · {{ $job['latest']['triggered_by'] }}</div>
                                        <div>{{ $job['latest']['finished_at'] ?? '—' }}</div>
                                        <div>↑{{ $job['latest']['updated'] }} / skip {{ $job['latest']['skipped'] }} / fail {{ $job['latest']['failed'] }}</div>
                                        @if (filled($job['latest']['error']))
                                            <div class="text-danger-600 dark:text-danger-400">{{ \Illuminate\Support\Str::limit($job['latest']['error'], 120) }}</div>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2">
                                    <div class="flex flex-wrap gap-2">
                                        <x-filament::button
                                            type="button"
                                            size="xs"
                                            wire:click="startDailyJob('{{ $job['key'] }}')"
                                            wire:confirm="Pokrenuti {{ $job['label'] }} odmah?"
                                            :disabled="! empty($job['locked'])"
                                        >
                                            Pokreni
                                        </x-filament::button>
                                        @if (! empty($job['latest']['id']) && ! empty($job['locked']))
                                            <x-filament::button
                                                type="button"
                                                size="xs"
                                                color="danger"
                                                wire:click="cancelDailyJob({{ $job['latest']['id'] }})"
                                            >
                                                Zaustavi
                                            </x-filament::button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($dailyRunHistory !== [])
                <div class="mt-6">
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Zadnjih 20 izvršenja</p>
                    <ul class="space-y-1 text-xs text-gray-600 dark:text-gray-300">
                        @foreach ($dailyRunHistory as $row)
                            <li>
                                #{{ $row['id'] }} {{ $row['job_key'] }} · {{ $row['status'] }} · {{ $row['triggered_by'] }}
                                · ↑{{ $row['updated'] }} fail {{ $row['failed'] }}
                                @if (($row['failed_ids'] ?? []) !== [])
                                    · IDs: {{ implode(', ', array_slice($row['failed_ids'], 0, 5)) }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-filament::section>
    </div>

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
                            <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Limit po batchu (1–2000, produkcija 1000–2000)</label>
                            <input
                                type="number"
                                min="1"
                                max="2000"
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
                        Import šalje <strong>redovnu</strong> BNC cijenu (<code>regularPrice</code>) i PDV tag 17 — bez akcijske cijene.
                        Ako GET <code>basePrice</code> ostane 0, PUT linked sync s <code>vat=17</code>.
                    </p>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Akcija — ručni test % (navedeni ID-evi)</p>
                    <div class="mb-3 max-w-md">
                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Merchant inventory ID-evi (zarez ili novi red)</label>
                        <textarea
                            wire:model="discountInventoryIds"
                            rows="2"
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900"
                            placeholder="2568416"
                        ></textarea>
                    </div>
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
                            <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Trajanje dana (SALE max 30)</label>
                            <input
                                type="number"
                                min="1"
                                max="30"
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
                            wire:confirm="Šalje POST /discounts (SALE, valuta merchant inventory — BAM, bez FX). Nastaviti?"
                            wire:loading.attr="disabled"
                        >
                            Akcija (live)
                        </x-filament::button>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Prazan unos ne šalje ništa. Automatska BNC akcija ide iz dnevnog posla u 02:30 (ne ovaj dugmić).
                    </p>
                </div>
            </div>
        </x-filament::section>
    </form>
</x-filament-panels::page>
