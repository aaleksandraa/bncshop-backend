<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\CanAccessLoyalty;
use App\Filament\Resources\LoyaltyCardResource\Pages;
use App\Models\LoyaltyCard;
use App\Services\Loyalty\LoyaltyCardService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LoyaltyCardResource extends Resource
{
    use CanAccessLoyalty;

    protected static ?string $model = LoyaltyCard::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $modelLabel = 'Loyalty kartica';

    protected static ?string $pluralModelLabel = 'Loyalty kartice';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return static::canAccessLoyaltyCards();
    }

    public static function canCreate(): bool
    {
        return static::canIssueCards();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer.user', 'issuedByUser']))
            ->columns([
                Tables\Columns\TextColumn::make('card_number')
                    ->label('Broj kartice')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('customer.user.name')
                    ->label('Kupac')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer.user.email')
                    ->label('E-mail')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'blocked', 'lost' => 'danger',
                        'replaced' => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Izdato')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('issuedByUser.name')
                    ->label('Izdao'),
            ])
            ->defaultSort('issued_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Aktivna',
                        'blocked' => 'Blokirana',
                        'lost' => 'Izgubljena',
                        'replaced' => 'Zamijenjena',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label('Štampaj')
                    ->icon('heroicon-o-printer')
                    ->url(fn (LoyaltyCard $record): string => route('filament.admin.loyalty-cards.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (LoyaltyCard $record): bool => $record->status === 'active'),
                Tables\Actions\Action::make('replace')
                    ->label('Zamijeni')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (LoyaltyCard $record): bool => $record->status === 'active' && static::canIssueCards())
                    ->requiresConfirmation()
                    ->action(function (LoyaltyCard $record, LoyaltyCardService $service): void {
                        $service->replaceCard($record, auth()->user());
                        Notification::make()->title('Kartica zamijenjena.')->success()->send();
                    }),
                Tables\Actions\Action::make('block')
                    ->label('Blokiraj')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (LoyaltyCard $record): bool => $record->status === 'active' && static::canBlockCards())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Razlog')
                            ->required(),
                    ])
                    ->action(function (LoyaltyCard $record, array $data, LoyaltyCardService $service): void {
                        $service->blockCard($record, $data['reason']);
                        Notification::make()->title('Kartica blokirana.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyCards::route('/'),
        ];
    }
}
