<x-filament-panels::page>
    @if (filled($pageLoadError))
        <div class="mb-6 rounded-lg border border-danger-300 bg-danger-50 p-4 dark:border-danger-600 dark:bg-danger-950">
            <p class="text-sm font-semibold text-danger-800 dark:text-danger-200">Stranica nije učitana</p>
            <p class="mt-1 text-sm text-danger-700 dark:text-danger-300">{{ $pageLoadError }}</p>
        </div>
    @endif

    <div @if ($dailyJobsActive) wire:poll.5s="refreshDailyJobsUi" @endif>
        <x-filament::section heading="Ritam" class="mb-6">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                Broj na katalogu je koliko je Ananas artikala povezano s BNC proizvodom.
                Ostali BNC proizvodi idu importom u 08:00. Export i catalog writes ostaju u Postavkama.
            </p>

            <div class="mb-4 flex flex-wrap items-center gap-3">
                <x-filament::button type="button" color="gray" wire:click="toggleDailyMaster">
                    Dnevni poslovi (cron): {{ $dailyJobsMasterEnabled ? 'uključeni' : 'isključeni' }}
                </x-filament::button>
            </div>

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
                                            <div class="text-danger-600 dark:text-danger-400">{{ \Illuminate\Support\Str::limit($job['latest']['error'], 180) }}</div>
                                        @endif
                                        @if (! empty($job['latest']['meta']['total_elements']))
                                            <div>Ananas lista: {{ $job['latest']['meta']['total_elements'] }}</div>
                                        @endif
                                        @foreach (($job['latest']['meta']['skip_reasons'] ?? []) as $reason => $count)
                                            <div>{{ $reason }}: {{ $count }}</div>
                                        @endforeach
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
                                @if (! empty($row['meta']['skip_reasons']))
                                    · {{ collect($row['meta']['skip_reasons'])->map(fn ($count, $reason) => $reason.' '.$count)->implode(', ') }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="Povezani proizvodi">
            <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                LINKED: <strong>{{ $linkedMappingCount }}</strong>
                @if ($mappingStatusCounts !== [])
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        @foreach ($mappingStatusCounts as $status => $count)
                            · {{ $status }} {{ $count }}
                        @endforeach
                    </span>
                @endif
            </p>
            @if ($linkedMappingSample !== [])
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[48rem] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                <th class="py-2 pr-3 font-medium">BNC</th>
                                <th class="py-2 pr-3 font-medium">Naziv</th>
                                <th class="py-2 pr-3 font-medium">EAN</th>
                                <th class="py-2 pr-3 font-medium">Ananas ID</th>
                                <th class="py-2 pr-3 font-medium">Status</th>
                                <th class="py-2 font-medium">Zadnje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($linkedMappingSample as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-3">{{ $row['product_id'] }}</td>
                                    <td class="py-2 pr-3">{{ $row['name'] }}</td>
                                    <td class="py-2 pr-3">{{ $row['ean'] ?: '—' }}</td>
                                    <td class="py-2 pr-3">{{ $row['inventory_id'] }}</td>
                                    <td class="py-2 pr-3">{{ $row['remote_status'] ?: '—' }}</td>
                                    <td class="py-2">{{ $row['last_success_at'] ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($linkedMappingCount > count($linkedMappingSample))
                    <p class="mt-2 text-xs text-gray-500">Prikazano {{ count($linkedMappingSample) }} od {{ $linkedMappingCount }}.</p>
                @endif
            @else
                <p class="text-sm text-gray-500">Nema povezanih proizvoda.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
