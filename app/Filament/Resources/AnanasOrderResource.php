<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\AnanasOrderResource\Pages;
use App\Models\AnanasOrder;
use App\Support\Ananas\AnanasOrderStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnanasOrderResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = AnanasOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Prodaja';

    protected static ?string $navigationLabel = 'Ananas narudžbe';

    protected static ?string $modelLabel = 'Ananas narudžba';

    protected static ?string $pluralModelLabel = 'Ananas narudžbe';

    protected static ?int $navigationSort = 2;

    protected static function permissionPrefix(): string
    {
        return 'orders';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $address = static fn (AnanasOrder $record): array => is_array($record->billing_address) ? $record->billing_address : [];

        return $infolist->schema([
            Infolists\Components\Section::make('Narudžba')
                ->schema([
                    Infolists\Components\TextEntry::make('ananas_order_id')->label('Ananas id'),
                    Infolists\Components\TextEntry::make('ananas_created_at')->label('Datum')->dateTime('d.m.Y H:i'),
                    Infolists\Components\TextEntry::make('status_bucket')->label('Status')->formatStateUsing(fn (string $state): string => AnanasOrderStatus::bucketLabel($state)),
                    Infolists\Components\TextEntry::make('remote_status')->label('Ananas status (sirovo)'),
                    Infolists\Components\TextEntry::make('payment_methods')
                        ->label('Plaćanje')
                        ->formatStateUsing(fn (AnanasOrder $record): string => $record->paymentLabel()),
                    Infolists\Components\TextEntry::make('total_price')
                        ->label('Ukupno')
                        ->formatStateUsing(fn (AnanasOrder $record): string => number_format((float) $record->total_price, 2, ',', '.').' '.$record->currency),
                ])
                ->columns(2),
            Infolists\Components\Section::make('Kupac')
                ->schema([
                    Infolists\Components\TextEntry::make('customer_name')
                        ->label('Ime')
                        ->state(fn (AnanasOrder $record): string => $record->customerName()),
                    Infolists\Components\TextEntry::make('billing_address.streetName')
                        ->label('Ulica')
                        ->state(fn (AnanasOrder $record): string => trim(implode(' ', array_filter([
                            $address($record)['streetName'] ?? '',
                            $address($record)['streetNumber'] ?? '',
                            $address($record)['apartmentNumber'] ?? '',
                        ])))),
                    Infolists\Components\TextEntry::make('billing_address.city')
                        ->label('Grad')
                        ->state(fn (AnanasOrder $record): string => trim(implode(' ', array_filter([
                            $address($record)['postcode'] ?? '',
                            $address($record)['city'] ?? '',
                        ])))),
                    Infolists\Components\TextEntry::make('billing_address.buyerId')->label('PIB'),
                    Infolists\Components\TextEntry::make('billing_address.buyerName')->label('Naziv pravnog lica'),
                ])
                ->columns(2),
            Infolists\Components\Section::make('Stavke')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('items')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('product_name')->label('Proizvod'),
                            Infolists\Components\TextEntry::make('product_ean')->label('EAN'),
                            Infolists\Components\TextEntry::make('quantity_ordered')->label('Naručeno'),
                            Infolists\Components\TextEntry::make('quantity_confirmed')->label('Potvrđeno')->placeholder('—'),
                            Infolists\Components\TextEntry::make('quantity_packed')->label('Spakovano')->placeholder('—'),
                            Infolists\Components\TextEntry::make('line_total')
                                ->label('Iznos')
                                ->formatStateUsing(fn ($state, $record): string => number_format((float) ($state ?? $record->unit_price), 2, ',', '.').' '.($record->order->currency ?? 'KM')),
                            Infolists\Components\TextEntry::make('product_id')
                                ->label('BNC proizvod')
                                ->formatStateUsing(fn ($state): string => $state ? 'ID '.$state : '—')
                                ->url(fn ($record): ?string => $record->product_id ? ProductResource::getUrl('edit', ['record' => $record->product_id]) : null),
                        ])
                        ->columns(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ananas_order_id')->label('Ananas id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('ananas_created_at')->label('Datum')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Kupac')
                    ->state(fn (AnanasOrder $record): string => $record->customerName())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('billing_address', 'like', '%'.$search.'%')),
                Tables\Columns\TextColumn::make('customer_city')
                    ->label('Grad')
                    ->state(fn (AnanasOrder $record): string => $record->customerCity()),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Ukupno')
                    ->formatStateUsing(fn (AnanasOrder $record): string => number_format((float) $record->total_price, 2, ',', '.').' '.$record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_methods')
                    ->label('Plaćanje')
                    ->formatStateUsing(fn (AnanasOrder $record): string => $record->paymentLabel())
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status_bucket')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AnanasOrderStatus::bucketLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        AnanasOrderStatus::BUCKET_AWAITING => 'warning',
                        AnanasOrderStatus::BUCKET_COMPLETED => 'success',
                        AnanasOrderStatus::BUCKET_CANCELLED => 'danger',
                        AnanasOrderStatus::BUCKET_ON_DELIVERY => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Stavke')
                    ->counts('items'),
            ])
            ->defaultSort('ananas_created_at', 'desc')
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnanasOrders::route('/'),
            'view' => Pages\ViewAnanasOrder::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canPullOrConfirm(): bool
    {
        return static::userCan('update');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('items');
    }
}
