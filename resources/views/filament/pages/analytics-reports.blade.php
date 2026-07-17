<x-filament-panels::page>
    <form wire:submit="applyFilters" class="mb-6">
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button type="submit">
                Primijeni filter
            </x-filament::button>
        </div>
    </form>

    <x-filament::tabs>
        <x-filament::tabs.item
            :active="$activeTab === 'period'"
            wire:click="$set('activeTab', 'period')"
        >
            Prodaja po periodu
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'product'"
            wire:click="$set('activeTab', 'product')"
        >
            Po proizvodu
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'category'"
            wire:click="$set('activeTab', 'category')"
        >
            Po kategoriji
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'brand'"
            wire:click="$set('activeTab', 'brand')"
        >
            Po brendu
        </x-filament::tabs.item>
    </x-filament::tabs>

    <div class="mt-6">
        @php
            $period = $this->getPeriodReport();
            $extras = $this->getDashboardExtras();
        @endphp

        @if ($activeTab === 'period')
            <div class="grid gap-4 md:grid-cols-4 mb-6">
                <x-filament::section>
                    <div class="text-sm text-gray-500">Prihod</div>
                    <div class="text-2xl font-semibold">{{ number_format($period['current']['revenue'], 2) }} KM</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">Narudžbe</div>
                    <div class="text-2xl font-semibold">{{ $period['current']['orders_count'] }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">Prodane stavke</div>
                    <div class="text-2xl font-semibold">{{ $period['current']['items_sold'] }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">AOV</div>
                    <div class="text-2xl font-semibold">{{ number_format($period['current']['avg_order_value'], 2) }} KM</div>
                </x-filament::section>
            </div>

            <x-filament::section heading="Dnevni pregled">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="py-2 text-left">Datum</th>
                                <th class="py-2 text-right">Prihod</th>
                                <th class="py-2 text-right">Narudžbe</th>
                                <th class="py-2 text-right">Stavke</th>
                                <th class="py-2 text-right">AOV</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($period['daily'] as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2">{{ $row['date'] }}</td>
                                    <td class="py-2 text-right">{{ number_format($row['revenue'], 2) }} KM</td>
                                    <td class="py-2 text-right">{{ $row['orders_count'] }}</td>
                                    <td class="py-2 text-right">{{ $row['items_sold'] }}</td>
                                    <td class="py-2 text-right">{{ number_format($row['avg_order_value'], 2) }} KM</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-gray-500">Nema podataka za odabrani period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @elseif ($activeTab === 'product')
            <x-filament::section heading="Top proizvodi">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="py-2 text-left">Proizvod</th>
                                <th class="py-2 text-right">Količina</th>
                                <th class="py-2 text-right">Prihod</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($extras['top_products'] as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2">{{ $row['name'] }}</td>
                                    <td class="py-2 text-right">{{ $row['qty'] }}</td>
                                    <td class="py-2 text-right">{{ number_format($row['revenue'], 2) }} KM</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-4 text-center text-gray-500">Nema podataka.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @elseif ($activeTab === 'category')
            <x-filament::section heading="Top kategorije">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="py-2 text-left">Kategorija</th>
                                <th class="py-2 text-right">Prihod</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($extras['top_categories'] as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2">{{ $row['name'] }}</td>
                                    <td class="py-2 text-right">{{ number_format($row['revenue'], 2) }} KM</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-4 text-center text-gray-500">Nema podataka.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @else
            <x-filament::section heading="Top brendovi">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="py-2 text-left">Brend</th>
                                <th class="py-2 text-right">Prihod</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($extras['top_brands'] as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2">{{ $row['name'] }}</td>
                                    <td class="py-2 text-right">{{ number_format($row['revenue'], 2) }} KM</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-4 text-center text-gray-500">Nema podataka.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
