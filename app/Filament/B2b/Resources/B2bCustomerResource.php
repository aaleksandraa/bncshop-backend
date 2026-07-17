<?php

namespace App\Filament\B2b\Resources;

use App\Filament\B2b\Resources\B2bCustomerResource\Pages;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Models\B2bCustomer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class B2bCustomerResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = B2bCustomer::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Kupci';

    protected static ?string $modelLabel = 'B2B kupac';

    protected static ?string $pluralModelLabel = 'B2B kupci';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'company_name';

    protected static function permissionPrefix(): string
    {
        return 'b2b_customers';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Kontakt')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Ime i prezime')
                    ->required()
                    ->maxLength(255)
                    ->visibleOn('create')
                    ->dehydrated(false),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->visibleOn('create')
                    ->dehydrated(false),
                Forms\Components\TextInput::make('phone')
                    ->label('Telefon')
                    ->required()
                    ->maxLength(50),
            ])->columns(2),
            Forms\Components\Section::make('Firma')->schema([
                Forms\Components\TextInput::make('company_name')
                    ->label('Naziv firme')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('company_address')
                    ->label('Adresa')
                    ->required()
                    ->rows(2),
                Forms\Components\TextInput::make('jib')
                    ->label('JIB')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('pdv_number')
                    ->label('PDV broj')
                    ->maxLength(50),
            ])->columns(2),
            Forms\Components\Section::make('Popust')->schema([
                Forms\Components\TextInput::make('discount_percent')
                    ->label('Popust (%)')
                    ->helperText('Ostavite prazno za globalni default popust.')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktivan')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name')->label('Firma')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Kontakt')->searchable(),
                Tables\Columns\TextColumn::make('user.email')->label('Email')->searchable(),
                Tables\Columns\TextColumn::make('discount_percent')->label('Popust %')->placeholder('Default'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListB2bCustomers::route('/'),
            'create' => Pages\CreateB2bCustomer::route('/create'),
            'edit' => Pages\EditB2bCustomer::route('/{record}/edit'),
        ];
    }
}
