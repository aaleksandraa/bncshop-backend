<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Filament\Concerns\CanAccessLoyalty;
use App\Services\Loyalty\LoyaltyService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyTransactionsRelationManager extends RelationManager
{
    use CanAccessLoyalty;

    protected static string $relationship = 'loyaltyTransactions';

    protected static ?string $title = 'BNC bodovi';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return static::canAccessLoyalty();
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Datum')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
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
            ->headerActions([
                Tables\Actions\Action::make('adjustPoints')
                    ->label('Korekcija bodova')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => static::canAccessLoyalty(requireUpdate: true))
                    ->form([
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
                        $customer = $this->getOwnerRecord();
                        $loyaltyService->adjustPoints(
                            $customer,
                            (int) $data['points'],
                            (string) $data['description'],
                        );

                        Notification::make()
                            ->title('Bodovi ažurirani.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
