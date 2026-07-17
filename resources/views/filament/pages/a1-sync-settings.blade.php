<x-filament-panels::page>
    @if ($sourceExists)
        @if (! empty($status['issues']))
            <div class="mb-6 rounded-lg border border-warning-300 bg-warning-50 p-4 dark:border-warning-600 dark:bg-warning-950">
                <p class="mb-2 text-sm font-semibold text-warning-800 dark:text-warning-200">Detektovani problemi sa sync-om</p>
                <ul class="list-inside list-disc space-y-1 text-sm text-warning-700 dark:text-warning-300">
                    @foreach ($status['issues'] as $issue)
                        <li>{{ $issue }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($status['has_running_job']) && ! empty($status['running_job']))
            <div class="mb-6 rounded-lg border border-info-300 bg-info-50 p-4 dark:border-info-600 dark:bg-info-950">
                <p class="text-sm font-medium text-info-800 dark:text-info-200">
                    Sync je u toku: job #{{ $status['running_job']['id'] }}
                    ({{ $status['running_job']['type'] }}) — traje {{ $status['running_job']['running_for'] ?? '?' }}
                </p>
                @if (! empty($status['running_job']['progress']))
                    @php $p = $status['running_job']['progress']; @endphp
                    <p class="mt-1 text-sm text-info-700 dark:text-info-300">
                        Napredak: {{ $p['pages'] ?? 0 }} stranica, {{ $p['products'] ?? 0 }} proizvoda
                        @if (! empty($p['last_page']))
                            (zadnja str. {{ $p['last_page'] }} ~{{ $p['last_page_duration_sec'] ?? '?' }}s)
                        @endif
                    </p>
                @endif
            </div>
        @endif

        <div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-filament::section>
                <div class="space-y-1">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Zadnji uspješni sync</p>
                    <p class="text-base font-semibold">
                        {{ isset($status['last_successful_sync_at']) && $status['last_successful_sync_at']
                            ? $status['last_successful_sync_at']->format('d.m.Y H:i')
                            : '— (potreban puni sync)' }}
                    </p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="space-y-1">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        @if (! empty($status['is_overdue']))
                            Sljedeći sync (zakasnio)
                        @else
                            Sljedeći planirani sync
                        @endif
                    </p>
                    <p @class([
                        'text-base font-semibold',
                        'text-danger-600 dark:text-danger-400' => ! empty($status['is_overdue']),
                    ])>
                        @if (! empty($status['is_overdue']))
                            Trebao {{ $status['next_sync_at']?->format('d.m.Y H:i') }}
                            ({{ $status['overdue_human'] ?? '' }})
                        @elseif (isset($status['next_sync_at']) && $status['next_sync_at'])
                            {{ $status['next_sync_at']->format('d.m.Y H:i') }}
                        @else
                            —
                        @endif
                    </p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="space-y-1">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status konekcije</p>
                    <p class="text-base font-semibold capitalize">
                        {{ $status['connection_status'] ?? 'unknown' }}
                    </p>
                    @if (! empty($status['last_error']))
                        <p class="text-xs text-danger-600 dark:text-danger-400">{{ $status['last_error'] }}</p>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="space-y-1">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Zadnji import job</p>
                    @if (! empty($status['latest_job']))
                        <p class="text-base font-semibold">
                            #{{ $status['latest_job']['id'] }}
                            — {{ $status['latest_job']['type'] }}
                            ({{ $status['latest_job']['status'] }})
                        </p>
                        @php
                            $imported = data_get($status, 'latest_job.stats.products.imported');
                        @endphp
                        @if ($imported !== null)
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $imported }} proizvoda</p>
                        @endif
                    @else
                        <p class="text-base font-semibold">—</p>
                    @endif
                </div>
            </x-filament::section>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        @if ($sourceExists)
            <div class="flex flex-wrap gap-3">
                <x-filament::button type="submit">
                    Sačuvaj postavke
                </x-filament::button>

                <x-filament::button type="button" color="gray" wire:click="testConnection">
                    Test konekcije
                </x-filament::button>

                <x-filament::button
                    type="button"
                    color="success"
                    wire:click="runIncrementalSync"
                    wire:confirm="Pokrenuti inkrementalni sync proizvoda (ModifiedAfter)?"
                >
                    Inkrementalni sync
                </x-filament::button>

                <x-filament::button
                    type="button"
                    color="warning"
                    wire:click="runFullSync"
                    wire:confirm="Puni sync uvozi kategorije, atribute i sve proizvode. Nastaviti?"
                >
                    Puni sync
                </x-filament::button>
            </div>
        @endif
    </form>
</x-filament-panels::page>
