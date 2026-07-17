<?php

namespace App\Filament\B2b\Resources;

use App\Filament\B2b\Resources\B2bCampaignResource\Pages;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Models\B2bCampaign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class B2bCampaignResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = B2bCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $modelLabel = 'B2B kampanja';

    protected static ?string $pluralModelLabel = 'B2B kampanje';

    protected static ?int $navigationSort = 3;

    protected static function permissionPrefix(): string
    {
        return 'b2b_campaigns';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Naziv')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('discount_type')
                ->label('Tip popusta')
                ->options([
                    'percent' => 'Procenat (%)',
                    'fixed_price' => 'Fiksna cijena (KM)',
                ])
                ->required()
                ->live(),
            Forms\Components\TextInput::make('value')
                ->label(fn (Forms\Get $get) => $get('discount_type') === 'fixed_price' ? 'Cijena (KM)' : 'Popust (%)')
                ->required()
                ->numeric()
                ->minValue(0),
            Forms\Components\DateTimePicker::make('starts_at')->label('Početak'),
            Forms\Components\DateTimePicker::make('ends_at')->label('Kraj'),
            Forms\Components\TextInput::make('badge_text')->label('Badge tekst')->maxLength(100),
            Forms\Components\Toggle::make('is_active')->label('Aktivna')->default(true),
            Forms\Components\Select::make('products')
                ->label('Proizvodi')
                ->relationship('products', 'name')
                ->multiple()
                ->searchable()
                ->preload()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('discount_type')->label('Tip'),
                Tables\Columns\TextColumn::make('value')->label('Vrijednost'),
                Tables\Columns\TextColumn::make('starts_at')->dateTime('d.m.Y H:i'),
                Tables\Columns\TextColumn::make('ends_at')->dateTime('d.m.Y H:i'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListB2bCampaigns::route('/'),
            'create' => Pages\CreateB2bCampaign::route('/create'),
            'edit' => Pages\EditB2bCampaign::route('/{record}/edit'),
        ];
    }
}
