<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\SupplierResource\RelationManagers;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $modelLabel = 'Dobavljač';

    protected static ?string $pluralModelLabel = 'Dobavljači';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('suppliers.view') ?? auth()->user()?->can('manage_products') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('suppliers.update') ?? auth()->user()?->can('manage_products') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Osnovno')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('API naziv')
                            ->disabled(),
                        Forms\Components\TextInput::make('display_name')
                            ->label('Prikazni naziv')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->label('Kod')
                            ->maxLength(64)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Redoslijed')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktivan')
                            ->default(true),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Kalkulacija cijene')
                    ->schema([
                        Forms\Components\TextInput::make('price_adjustment_amount')
                            ->label('Fiksni dodatak na cijenu')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(9999)
                            ->default(0)
                            ->suffix('KM')
                            ->helperText('Fiksni iznos koji se dodaje na API/prikaznu cijenu (api_price i api_final_price). Primjenjuje se na sve postojeće proizvode ovog dobavljača pri spremanju. 0 = bez dodatka.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Naziv')
                    ->searchable(['display_name', 'name', 'code'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Kod')
                    ->badge(),
                Tables\Columns\TextColumn::make('product_offers_count')
                    ->label('Proizvoda')
                    ->counts('productOffers'),
                Tables\Columns\TextColumn::make('margin_rules_count')
                    ->label('Pravila marže')
                    ->counts('marginRules'),
                Tables\Columns\TextColumn::make('price_adjustment_amount')
                    ->label('Dodatak cijene')
                    ->formatStateUsing(fn (Supplier $record): string => $record->hasPriceAdjustment()
                        ? '+'.number_format((float) $record->price_adjustment_amount, 2, '.', '').' KM'
                        : '—')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktivan')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Redoslijed')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktivan'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MarginRulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
