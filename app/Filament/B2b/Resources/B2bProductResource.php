<?php

namespace App\Filament\B2b\Resources;

use App\Filament\Support\OptimizedMediaUpload;
use App\Filament\B2b\Resources\B2bProductResource\Pages;
use App\Filament\B2b\Concerns\BuildsB2bProductAttributeFields;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Models\B2bProduct;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class B2bProductResource extends Resource
{
    use AuthorizesWithPermissions;
    use BuildsB2bProductAttributeFields;

    protected static ?string $model = B2bProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $modelLabel = 'B2B proizvod';

    protected static ?string $pluralModelLabel = 'B2B proizvodi';

    protected static ?int $navigationSort = 2;

    protected static function permissionPrefix(): string
    {
        return 'b2b_products';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Osnovno')->schema([
                Forms\Components\Select::make('b2b_category_id')
                    ->label('Kategorija')
                    ->relationship('category', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live(),
                Forms\Components\TextInput::make('name')
                    ->label('Naziv')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('sku')
                    ->label('Šifra')
                    ->maxLength(100),
                Forms\Components\RichEditor::make('description')
                    ->label('Opis')
                    ->columnSpanFull(),
            ])->columns(2),
            ...static::b2bProductAttributeFields(),
            Forms\Components\Section::make('Cijena i zalihe')->schema([
                Forms\Components\TextInput::make('regular_price')
                    ->label('Redovna cijena (KM)')
                    ->required()
                    ->numeric()
                    ->minValue(0),
                Forms\Components\TextInput::make('sale_price')
                    ->label('Cijena na sniženju (KM)')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\Toggle::make('exclude_customer_discount')
                    ->label('Isključi kupčev popust')
                    ->helperText('Ako je uključeno, kupac vidi samo redovnu/akcijsku cijenu proizvoda.'),
                Forms\Components\TextInput::make('stock_quantity')
                    ->label('Količina na zalihi')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Redoslijed')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktivan')
                    ->default(true),
            ])->columns(2),
            Forms\Components\Section::make('Slike')->schema([
                Forms\Components\Repeater::make('images')
                    ->relationship()
                    ->label('Slike proizvoda')
                    ->schema([
                        OptimizedMediaUpload::configure(
                            Forms\Components\FileUpload::make('path')
                                ->label('Slika')
                                ->image()
                                ->required(),
                            'b2b-products',
                        ),
                        Forms\Components\Toggle::make('is_primary')
                            ->label('Primarna'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Redoslijed')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->defaultItems(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Naziv')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')->label('Kategorija')->sortable(),
                Tables\Columns\TextColumn::make('regular_price')->label('Cijena')->money('BAM'),
                Tables\Columns\TextColumn::make('sale_price')->label('Sniženje')->money('BAM')->placeholder('—'),
                Tables\Columns\TextColumn::make('stock_quantity')->label('Zaliha')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktivan')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('b2b_category_id')
                    ->label('Kategorija')
                    ->relationship('category', 'name'),
            ])
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
            'index' => Pages\ListB2bProducts::route('/'),
            'create' => Pages\CreateB2bProduct::route('/create'),
            'edit' => Pages\EditB2bProduct::route('/{record}/edit'),
        ];
    }
}
