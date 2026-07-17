<x-filament-panels::page>
    <form wire:submit="searchCustomer" class="space-y-6">
        {{ $this->searchForm }}
        <x-filament::button type="submit">Pretraži kupca</x-filament::button>
    </form>

    @if($selectedCustomer)
        <div class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Kupac</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-sm text-gray-500">Ime</p>
                    <p class="font-medium">{{ $selectedCustomer->user?->name }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">E-mail</p>
                    <p class="font-medium">{{ $selectedCustomer->user?->email }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Stanje bodova</p>
                    <p class="font-medium text-success-600">{{ number_format($selectedCustomer->loyalty_points_balance) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Kartica</p>
                    <p class="font-medium">{{ $selectedCustomer->activeLoyaltyCard()?->card_number ?? 'Nema aktivne kartice' }}</p>
                </div>
            </div>

            @if($selectedCustomer->activeLoyaltyCard() === null && filled($selectedCustomer->user?->email))
                <div class="mt-4">
                    <x-filament::button
                        type="button"
                        wire:click="issueCard"
                        icon="heroicon-o-credit-card"
                    >
                        Izdaj loyalty karticu
                    </x-filament::button>
                </div>
            @elseif($selectedCustomer->activeLoyaltyCard() === null)
                <p class="mt-4 text-sm text-danger-600">
                    Kupac nema e-mail adresu — loyalty kartica se može izdati samo registrovanom kupcu s e-mailom.
                </p>
            @endif
        </div>

        <div class="mt-8 grid gap-8 lg:grid-cols-2">
            <form wire:submit="recordSale" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-base font-semibold">Evidentiraj kupovinu</h3>
                {{ $this->saleForm }}
                <x-filament::button type="submit">Dodijeli bodove</x-filament::button>
            </form>

            <form wire:submit="redeemReward" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-base font-semibold">Iskoristi nagradu</h3>
                {{ $this->redeemForm }}
                <x-filament::button type="submit" color="warning">Iskoristi nagradu</x-filament::button>
            </form>
        </div>

        @if(count($recentTransactions) > 0)
            <div class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-base font-semibold">Zadnje transakcije u radnji</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="pb-2 pr-4">Datum</th>
                                <th class="pb-2 pr-4">Tip</th>
                                <th class="pb-2 pr-4">Bodovi</th>
                                <th class="pb-2">Opis</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentTransactions as $transaction)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-4">{{ $transaction['date'] }}</td>
                                    <td class="py-2 pr-4">{{ $transaction['type'] }}</td>
                                    <td class="py-2 pr-4">{{ $transaction['points'] }}</td>
                                    <td class="py-2">{{ $transaction['description'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</x-filament-panels::page>
