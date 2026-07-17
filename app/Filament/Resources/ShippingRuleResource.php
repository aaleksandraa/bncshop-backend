<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\ShippingRuleResource\Pages;
use App\Models\ShippingRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingRuleResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = ShippingRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Prodaja';

    protected static ?string $modelLabel = 'Pravilo dostave';

    protected static ?string $pluralModelLabel = 'Pravila dostave';

    protected static ?int $navigationSort = 5;

    protected static function permissionPrefix(): string
    {
        return 'shipping_rules';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Naziv')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->label('Tip')
                    ->options([
                        'global' => 'Globalno',
                        'category' => 'Po kategoriji',
                    ])
                    ->required()
                    ->live(),
                Forms\Components\Select::make('category_id')
                    ->label('Kategorija')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => $get('type') === 'category'),
                Forms\Components\TextInput::make('fixed_fee')
                    ->label('Fiksna naknada')
                    ->numeric()
                    ->prefix('KM')
                    ->default(0),
                Forms\Components\TextInput::make('free_threshold')
                    ->label('Besplatna dostava od')
                    ->numeric()
                    ->prefix('KM'),
                Forms\Components\TextInput::make('priority')
                    ->label('Prioritet')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('pickup_enabled')
                    ->label('Preuzimanje u radnji')
                    ->default(true),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktivno')
                    ->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naziv')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->badge(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategorija'),
                Tables\Columns\TextColumn::make('fixed_fee')
                    ->label('Naknada')
                    ->money('BAM'),
                Tables\Columns\TextColumn::make('free_threshold')
                    ->label('Besplatno od')
                    ->money('BAM'),
                Tables\Columns\TextColumn::make('priority')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktivno')
                    ->boolean(),
            ])
            ->defaultSort('priority')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingRules::route('/'),
            'create' => Pages\CreateShippingRule::route('/create'),
            'edit' => Pages\EditShippingRule::route('/{record}/edit'),
        ];
    }
}
