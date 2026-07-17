<?php

namespace App\Filament\Resources;

use App\Filament\Pages\OlxSyncSettingsPage;
use App\Filament\Resources\OlxListingRegistryResource\Pages;
use App\Models\OlxListingRegistry;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OlxListingRegistryResource extends Resource
{
    protected static ?string $model = OlxListingRegistry::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'OLX';

    protected static ?string $navigationLabel = 'Legacy oglasi';

    protected static ?string $modelLabel = 'OLX legacy oglas';

    protected static ?string $pluralModelLabel = 'OLX legacy oglasi';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return OlxSyncSettingsPage::canAccess();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('olx_listing_id')->disabled(),
            Forms\Components\TextInput::make('title')->disabled(),
            Forms\Components\Select::make('product_id')
                ->label('BNC proizvod')
                ->options(fn (): array => Product::query()->orderBy('name')->limit(500)->pluck('name', 'id')->all())
                ->searchable(),
            Forms\Components\Select::make('sync_mode')
                ->options([
                    OlxListingRegistry::SYNC_MODE_LEGACY => 'Legacy (ne diraj)',
                    OlxListingRegistry::SYNC_MODE_MANAGED => 'Managed (sync dozvoljen)',
                ])
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('olx_listing_id')->label('OLX ID')->sortable(),
                Tables\Columns\TextColumn::make('title')->limit(40)->searchable(),
                Tables\Columns\TextColumn::make('sku_number')->label('SKU'),
                Tables\Columns\TextColumn::make('product.name')->label('BNC proizvod'),
                Tables\Columns\TextColumn::make('sync_mode')->badge(),
                Tables\Columns\TextColumn::make('match_method')->label('Match'),
                Tables\Columns\TextColumn::make('imported_at')->dateTime('d.m.Y H:i'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('sync_mode')
                    ->options([
                        OlxListingRegistry::SYNC_MODE_LEGACY => 'Legacy',
                        OlxListingRegistry::SYNC_MODE_MANAGED => 'Managed',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('takeover')
                    ->label('Preuzmi upravljanje')
                    ->icon('heroicon-o-hand-raised')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (OlxListingRegistry $record): bool => $record->sync_mode === OlxListingRegistry::SYNC_MODE_LEGACY)
                    ->action(function (OlxListingRegistry $record): void {
                        $record->update(['sync_mode' => OlxListingRegistry::SYNC_MODE_MANAGED]);

                        if ($record->product_id !== null) {
                            Product::query()->whereKey($record->product_id)->update([
                                'olx_managed' => true,
                                'olx_listing_id' => (string) $record->olx_listing_id,
                            ]);
                        }

                        Notification::make()->title('Upravljanje preuzeto')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageOlxListingRegistries::route('/'),
        ];
    }
}
