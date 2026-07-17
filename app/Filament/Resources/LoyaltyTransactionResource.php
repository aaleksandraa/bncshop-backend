<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\CanAccessLoyalty;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Services\Loyalty\LoyaltyService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyTransactionResource extends Resource
{
    use CanAccessLoyalty;

    protected static ?string $model = LoyaltyTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $modelLabel = 'Transakcija bodova';

    protected static ?string $pluralModelLabel = 'Transakcije bodova';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return static::canAccessLoyalty();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Datum')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.user.email')
                    ->label('Kupac')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->badge(),
                Tables\Columns\TextColumn::make('points')
                    ->label('Bodovi')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Stanje')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Opis')
                    ->wrap(),
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Narudžba'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tip')
                    ->options([
                        'earn' => 'Zarada',
                        'earn_in_store' => 'Zarada (radnja)',
                        'redeem' => 'Iskorištenje',
                        'redeem_in_store' => 'Iskorištenje (radnja)',
                        'expire' => 'Istek',
                        'clawback' => 'Povrat',
                        'adjust' => 'Korekcija',
                        'claim_pending' => 'Preuzimanje',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('adjustPoints')
                    ->label('Ručna korekcija')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => static::canAccessLoyalty(requireUpdate: true))
                    ->form([
                        Forms\Components\Select::make('customer_id')
                            ->label('Kupac')
                            ->options(fn (): array => Customer::query()
                                ->with('user')
                                ->get()
                                ->mapWithKeys(fn (Customer $customer): array => [
                                    $customer->id => $customer->user?->email ?? "Kupac #{$customer->id}",
                                ])
                                ->all())
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('points')
                            ->label('Bodovi (+/-)')
                            ->numeric()
                            ->required()
                            ->helperText('Pozitivan broj dodaje, negativan oduzima bodove.'),
                        Forms\Components\Textarea::make('description')
                            ->label('Razlog')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (array $data, LoyaltyService $loyaltyService): void {
                        $customer = Customer::query()->findOrFail($data['customer_id']);
                        $loyaltyService->adjustPoints($customer, (int) $data['points'], (string) $data['description']);

                        Notification::make()
                            ->title('Bodovi ažurirani.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => LoyaltyTransactionResource\Pages\ListLoyaltyTransactions::route('/'),
        ];
    }
}
